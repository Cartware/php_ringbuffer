<?php
declare(strict_types=1);

namespace Cartware\RingBuffer;

class RingBuffer
    implements \Serializable, \Iterator, \ArrayAccess, \Countable
{

    CONST DEFAULT_LENGTH = 100;

    CONST FLAG_ALLOW_OVERWRITE = 1;

    /**
     * @var int
     */
    protected $_start = 0;

    /**
     * @var int
     */
    protected $_end = 0;

    /**
     * @var int
     */
    protected $_position = 0;

    /**
     * @var int
     */
    protected $_length = 0;

    /**
     * @var \SplFixedArray
     */
    protected $_data = NULL;

    /**
     * @var int
     */
    protected $_flags = 0;

    /**
     * Constructor
     */
    public function __construct(int $length = NULL, $flags = 0)
    {
        if ($length === NULL) {
            $length = static::DEFAULT_LENGTH; // static vs. self
        }

        $this->_flags = $flags;
        $this->_length = $length;
        $this->initializeFixedArray($length);
    }

    /**
     * @param int $length
     * @return void
     */
    private function initializeFixedArray(int $length)
    {
        $this->_data = new \SplFixedArray($length);
    }

    /**
     * @param $searchValue
     * @param $strict
     * @return bool
     */
    public function containsValue($searchValue, bool $strict = FALSE): bool
    {
        foreach ($this->_data as $key => $value) {
            if ($value == $searchValue) {
                if (!$strict || $value === $searchValue) {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * @param mixed $value
     * @return self
     */
    public function push($value): self
    {
        $key = $this->_end++;

        if (isset($this->_data[$key])) {
            if (!($this->_flags & self::FLAG_ALLOW_OVERWRITE)) {
                throw new \OverflowException('Ring buffer overflow', 1640010335);
            }

//            if ($this->_end >= $this->_start) {
//                $this->_start++;
//            }
        }

        $this->_data[$key] = $value;

        if ($this->_end >= $this->_length) {
            $this->_end = 0;
            $this->_start = max(1, $this->_start);
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function pop()
    {
        $key = $this->_start - 1;

        if (!$this->_data->offsetExists($key)) {
            throw new \UnderflowException('Ring buffer is empty', 1640010494);
        }

        $value = $this->_data[$key];
        unset($this->_data[$key]);
        $this->_start++;

        return $value;
    }

    /**
     * Free the whole ring buffer
     *
     * @return self
     */
    public function free(): self
    {
        foreach (array_keys((array) $this->_data) as $key) {
            unset($this->_data[$key]);
        }

        if (gc_enabled()) {
            gc_collect_cycles();
        }

        $this->rewind();

        return $this;
    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    // Implementation of <ArrayAccess>
    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @param int $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    /**
     * @param int $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->_data[$offset];
    }

    /**
     * @param int $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->_data[$offset] = $value;
    }

    /**
     * @param int $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    // Implementation of <Countable>
    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @param int $mode
     * @return int|null
     */
    public function count(int $mode = COUNT_NORMAL): int
    {
        $count = $this->_end - $this->_start;

        if ($this->_end < $this->_start) {
            $count = $this->_length - $this->_start + $this->_end + 1;
        }

        return $count;
    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    // Implementation of <Iterator>
    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @return void
     */
    function rewind() {
        reset($this->_data);
        $this->_position = $this->_start;
    }

    /**
     * @return mixed
     */
    function current() {
        return $this->_data[$this->_position];
    }

    /**
     * @return bool|float|int|mixed|string|null
     */
    function key() {
        return $this->_position;
    }

    /**
     * @return void
     */
    function next() {
        ++$this->_position;
        if ($this->_position >= $this->_length) {
            $this->_position = 0;
        }
    }

    /**
     * @return bool
     */
    function valid() {
        return $this->_position !== $this->_end;
    }

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    // Implementation of <Serializable>
    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * Serialize the configuration
     *
     * @return string
     */
    public function serialize()
    {
        $data = array();
        $properties = array('_start', '_end', '_position', '_length', '_flags');

        foreach ($properties as $property) {
            $data[$property] = $this->{$property};
        }

        $data['_data'] = (array) $this->_data;

        return serialize($data);
    }

    /**
     * Unserialize the configuration
     *
     * @param string $serialized
     * @return self
     */
    public function unserialize($serialized): self
    {
        $data = unserialize($serialized);

        foreach ($data as $key => $value) {
            if ($key !== '_data') {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }

        $this->initializeFixedArray($this->_length);

        foreach ($data['_data'] as $key => $value) {
            if ($key < $this->_length) {
                $this->_data[$key] = $value;
            }
        }
    }
}
