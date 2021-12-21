<?php
declare(strict_types=1);

namespace Cartware\RingBuffer\Test;

use Cartware\RingBuffer\RingBuffer;
use PHPUnit\Framework\TestCase;

class RingBufferTest extends TestCase {

    public function testPopEmptyRingBufferRaisesUndeflowException()
    {
        $ringBuffer = new RingBuffer();
        $this->expectException(\UnderflowException::class);
        $ringBuffer->pop();
    }

    public function testPushMoreThanCapacityRaisesOverflowException()
    {
        $ringBuffer = new RingBuffer(1);
        $ringBuffer->push(1);
        $this->expectException(\OverflowException::class);
        $ringBuffer->push(2);
        $ringBuffer->pop();
    }

    public function testPushMoreThanCapacityDoesNotRaiseOverflowExceptionWhenOverwriteIsAllowed()
    {
        $ringBuffer = new RingBuffer(2, RingBuffer::FLAG_ALLOW_OVERWRITE);
        $ringBuffer->push(1);
        $ringBuffer->push(2);
        $ringBuffer->push(3);
        $this->assertEquals(3, $ringBuffer->pop());
        $this->assertEquals(2, $ringBuffer->pop());
        $this->expectException(\UnderflowException::class);
        $ringBuffer->pop();
    }

    public function testCastRingBufferToArray()
    {
        $ringBuffer = new RingBuffer(2);
        $ringBuffer->push(1);
        $ringBuffer[] = 2;
        $array = $ringBuffer->toArray();
        $this->assertIsArray($array);
        $this->assertEquals([1, 2], $array);
    }

    public function testCastRingBufferToJson()
    {
        $ringBuffer = (new RingBuffer(2))->withValues(['a', 'b']);
        $this->assertEquals(json_encode(['a', 'b']), $ringBuffer->toJson());
    }

    public function testUseRingBufferAsArray()
    {
        $ringBuffer = new RingBuffer(2);
        $ringBuffer->push(1);
        $ringBuffer[] = 2;
        $this->assertEquals(1, $ringBuffer->pop());
        $this->assertEquals(2, $ringBuffer->pop());
    }

    public function testPopulateRingBufferWithValues()
    {
        $ringBuffer = (new RingBuffer(2))->withValues(['a', 'b']);
        $this->assertEquals('a', $ringBuffer->pop());
        $this->assertEquals('b', $ringBuffer->pop());
    }

    public function testFilterMethod()
    {
        $ringBuffer = (new RingBuffer(10))->withValues(range(1, 10));
        $ringBuffer->filter(static function(int $num) {
            return $num % 2 === 0;
        });

        $this->assertEquals([2, 4, 6, 8, 10, null, null, null, null, null], $ringBuffer->toArray());
    }

    public function testMapMethod()
    {
        $array = range(1, 10);
        $ringBuffer = (new RingBuffer(10))->withValues($array);
        $ringBuffer->map(static function(int $num) {
            return $num * $num;
        });

        $reference = array_map(static function(int $num) {
            return $num * $num;
        }, $array);

        $this->assertEquals($reference, $ringBuffer->toArray());
    }

    public function testCountMethod()
    {
        $ringBuffer = (new RingBuffer(10))->withValues(range(1, 10));

        $this->assertCount(10, $ringBuffer);

        $ringBuffer->filter(static function(int $num) {
            return $num % 2 === 0;
        });

        $this->assertCount(5, $ringBuffer);
    }

    public function testSortMethod()
    {
        $array = $sortedArray = [5, 8, 2, 7, 3, 6, 9, 1, 4, 10];
        sort($sortedArray);
        $ringBuffer = (new RingBuffer(10))->withValues($array);
        $ringBuffer->sort();
        $this->assertEquals($sortedArray, $ringBuffer->toArray());

        $alphabeticalReference = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k'];
        shuffle($alphabeticalReference);

        $sortCallback = static function(int $a, int $b) use ($alphabeticalReference) {
            return strcmp($alphabeticalReference[$a], $alphabeticalReference[$b]);
        };

        usort($sortedArray, $sortCallback);
        $ringBuffer->sort($sortCallback);

        $this->assertEquals($sortedArray, $ringBuffer->toArray());
    }

    public function testSerialization()
    {
        $array = range(1, 10);
        $ringBuffer = (new RingBuffer(10))->withValues($array);
        $serializedBuffer = serialize($ringBuffer);
        $this->assertIsString($serializedBuffer);
        $this->assertStringStartsWith('C:' . strlen(RingBuffer::class) . ':"' . RingBuffer::class . '":', $serializedBuffer);
        $restoredBuffer = unserialize($serializedBuffer, ['allowed_classes' => true]);
        $this->assertEquals($serializedBuffer, serialize($restoredBuffer));
    }

}