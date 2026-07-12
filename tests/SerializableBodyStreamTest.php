<?php

namespace Kevinrob\GuzzleCache\Tests;

use Kevinrob\GuzzleCache\SerializableBodyStream;
use PHPUnit\Framework\TestCase;

class SerializableBodyStreamTest extends TestCase
{
    public function testToStringReturnsTheWholeBody()
    {
        $stream = new SerializableBodyStream('Hello world!');
        $stream->read(5);

        $this->assertEquals('Hello world!', (string) $stream);
    }

    public function testCanReadAllContentWhenIteratedEnough()
    {
        $originalString = 'Not so long';
        $stream = new SerializableBodyStream($originalString);

        $got = '';
        while (!$stream->eof()) {
            $got .= $stream->read(1);
        }
        $this->assertEquals($originalString, $got);
        $this->assertEquals('', $stream->read(1));
    }

    public function testSerializationRoundTripPreservesTheBody()
    {
        $stream = new SerializableBodyStream('Hello world!');
        $stream->read(5);

        /** @var SerializableBodyStream $restored */
        $restored = unserialize(serialize($stream), ['allowed_classes' => [SerializableBodyStream::class]]);

        $this->assertEquals('Hello world!', (string) $restored);
        $this->assertEquals(0, $restored->tell());
    }

    public function testIsNotWritable()
    {
        $stream = new SerializableBodyStream('Hello world!');

        $this->assertFalse($stream->isWritable());
        $this->expectException(\RuntimeException::class);
        $stream->write('nope');
    }
}
