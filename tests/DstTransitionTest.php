<?php

namespace Kevinrob\GuzzleCache\Tests;

use Kevinrob\GuzzleCache\CacheEntry;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Test for DST transition issue where DateTime('-1 seconds') can create
 * future timestamps during timezone transitions.
 * 
 * This test verifies the fix for issue #194.
 */
class DstTransitionTest extends TestCase
{
    private $originalTimezone;

    protected function setUp(): void
    {
        // Save original timezone
        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        // Restore original timezone
        date_default_timezone_set($this->originalTimezone);
    }

    /**
     * Test CacheEntry behavior with UTC timestamp approach
     * This ensures that cache entries marked for immediate expiry
     * behave consistently across timezones.
     */
    public function testCacheEntryWithUtcTimestampIsAlwaysStale()
    {
        $timezones = ['UTC', 'Europe/Berlin', 'America/New_York'];
        
        foreach ($timezones as $timezone) {
            date_default_timezone_set($timezone);
            
            $request = new Request('GET', 'http://example.com');
            $response = new Response(200, [], 'test content');
            
            // Create entry with UTC timestamp approach (the fix)
            $entry = new CacheEntry($request, $response, new \DateTime('@' . (time() - 1)));
            
            // Should always be stale regardless of timezone
            $this->assertTrue($entry->isStale(), "Entry should be stale in timezone: $timezone");
            $this->assertFalse($entry->isFresh(), "Entry should not be fresh in timezone: $timezone");
            $this->assertGreaterThan(0, $entry->getStaleAge(), "Stale age should be positive in timezone: $timezone");
        }
    }

    /**
     * Test that validates the TTL calculation is correct
     */
    public function testCacheEntryTtlWithUtcTimestamp()
    {
        date_default_timezone_set('Europe/Berlin');
        
        $request = new Request('GET', 'http://example.com');
        $response = new Response(200, [], 'test content');
        
        // Create entry with UTC timestamp approach
        $entry = new CacheEntry($request, $response, new \DateTime('@' . (time() - 1)));
        
        $ttl = $entry->getTTL();
        
        // TTL should be -1 for expired entries without validation info
        $this->assertEquals(-1, $ttl, "TTL should be -1 for expired entries without validation info");
    }
}