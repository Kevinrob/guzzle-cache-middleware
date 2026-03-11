<?php

namespace Kevinrob\GuzzleCache\Tests;

use Kevinrob\GuzzleCache\CacheEntry;
use PHPUnit\Framework\TestCase;

class CorruptedCacheTest extends TestCase
{
    /**
     * This test ensures that when an internal stream object is corrupted 
     * (e.g. from an incompatible version), it throws an exception during unserialize.
     */
    public function testRestoreStreamBodyWithBrokenMessageThrowsTypeError()
    {
        $reflection = new \ReflectionClass(CacheEntry::class);
        $method = $reflection->getMethod('restoreStreamBody');

        $brokenMessage = $this->getMockBuilder(\Psr\Http\Message\MessageInterface::class)->getMock();
        $brokenStream = $this->getMockBuilder(\Psr\Http\Message\StreamInterface::class)->getMock();
        
        $brokenMessage->method('getBody')->willReturn($brokenStream);
        
        // Simulate a TypeError when casting to string (typical Guzzle fseek error)
        $brokenStream->method('__toString')->willThrowException(new \TypeError("fseek(): Argument #1 (\$stream) must be of type resource, int given"));

        $this->expectException(\TypeError::class);
        $method->invoke(null, $brokenMessage);
    }

    /**
     * This test ensures that __unserialize strictly invalidates the object if it's corrupted.
     */
    public function testUnserializeThrowsExceptionWhenStreamIsCorrupted()
    {
        $brokenMessage = $this->getMockBuilder(\Psr\Http\Message\MessageInterface::class)->getMock();
        $brokenStream = $this->getMockBuilder(\Psr\Http\Message\StreamInterface::class)->getMock();
        $brokenMessage->method('getBody')->willReturn($brokenStream);
        $brokenStream->method('__toString')->willThrowException(new \TypeError("Corrupted stream"));

        $cacheEntry = $this->getMockBuilder(CacheEntry::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        
        $data = [
            'request' => $brokenMessage,
            'response' => $brokenMessage,
            'staleAt' => clone \Kevinrob\GuzzleCache\Clock::now(),
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Corrupted cache entry');
        
        $cacheEntry->__unserialize($data);
    }
}
