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

}