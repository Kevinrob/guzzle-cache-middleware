<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use PHPUnit\Framework\TestCase;

class AuthorizationCacheTest extends TestCase
{
    /**
     * Test that requests with Authorization header are NOT cached when response only has max-age
     */
    public function testAuthorizationHeaderNotCachedWithMaxAge()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 'max-age=3600'
        ], 'Private data');

        $result = $strategy->cache($request, $response);
        $this->assertFalse($result, 'Request with Authorization header should not be cached with only max-age');

        $cached = $strategy->fetch($request);
        $this->assertNull($cached, 'No cache entry should exist for authorized request with only max-age');
    }

    /**
     * Test that requests with Authorization header ARE cached when response has Cache-Control: public
     */
    public function testAuthorizationHeaderCachedWithPublic()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 'public, max-age=3600'
        ], 'Public data');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Request with Authorization header should be cached with public directive');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist for authorized request with public directive');
        $this->assertEquals('Public data', (string) $cached->getResponse()->getBody());
    }

    /**
     * Test that requests with Authorization header ARE cached when response has Cache-Control: must-revalidate
     */
    public function testAuthorizationHeaderCachedWithMustRevalidate()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 'must-revalidate, max-age=3600'
        ], 'Revalidate data');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Request with Authorization header should be cached with must-revalidate directive');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist for authorized request with must-revalidate directive');
        $this->assertEquals('Revalidate data', (string) $cached->getResponse()->getBody());
    }

    /**
     * Test that requests with Authorization header ARE cached when response has Cache-Control: s-maxage
     */
    public function testAuthorizationHeaderCachedWithSMaxage()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 's-maxage=1800, max-age=3600'
        ], 'Shared cache data');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Request with Authorization header should be cached with s-maxage directive');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist for authorized request with s-maxage directive');
        $this->assertEquals('Shared cache data', (string) $cached->getResponse()->getBody());
    }

    /**
     * Test that requests WITHOUT Authorization header are cached normally with max-age
     */
    public function testNoAuthorizationHeaderCachedWithMaxAge()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PrivateCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data');
        
        $response = new Response(200, [
            'Cache-Control' => 'max-age=3600'
        ], 'Public data');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Request without Authorization header should be cached normally');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist for non-authorized request');
        $this->assertEquals('Public data', (string) $cached->getResponse()->getBody());
    }

    /**
     * Test PublicCacheStrategy behavior with Authorization headers
     */
    public function testPublicCacheStrategyWithAuthorization()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        // Test that private cache with authorization is not cached in public strategy
        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 'private, max-age=3600'
        ], 'Private data');

        $result = $strategy->cache($request, $response);
        $this->assertFalse($result, 'Private response should not be cached in public strategy');

        // Test that public response with authorization is cached
        $response2 = new Response(200, [
            'Cache-Control' => 'public, max-age=3600'
        ], 'Public data');

        $result2 = $strategy->cache($request, $response2);
        $this->assertTrue($result2, 'Public response with authorization should be cached in public strategy');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist for public authorized request');
        $this->assertEquals('Public data', (string) $cached->getResponse()->getBody());
    }

    /**
     * Test multiple allowed directives together
     */
    public function testMultipleAllowedDirectives()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 'public, must-revalidate, s-maxage=1800, max-age=3600'
        ], 'Multi directive data');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Request with Authorization header should be cached with multiple allowed directives');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist for authorized request with multiple allowed directives');
        $this->assertEquals('Multi directive data', (string) $cached->getResponse()->getBody());
    }

    /**
     * Test case sensitivity of Authorization header
     */
    public function testAuthorizationHeaderCaseSensitivity()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        $requests = [
            new Request('GET', 'https://api.example.com/data', ['Authorization' => 'Bearer token']),
            new Request('GET', 'https://api.example.com/data', ['authorization' => 'Bearer token']),
            new Request('GET', 'https://api.example.com/data', ['AUTHORIZATION' => 'Bearer token']),
        ];
        
        $response = new Response(200, [
            'Cache-Control' => 'max-age=3600'
        ], 'Test data');

        foreach ($requests as $request) {
            $result = $strategy->cache($request, $response);
            $this->assertFalse($result, 'Authorization header should be detected regardless of case');

            $cached = $strategy->fetch($request);
            $this->assertNull($cached, 'No cache entry should exist for any case variation of Authorization header');
        }
    }

    /**
     * Test that other cache control directives still work as expected
     */
    public function testOtherCacheControlDirectivesStillWork()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PublicCacheStrategy($storage);

        // Test no-store still prevents caching even with authorization allowances
        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        $response = new Response(200, [
            'Cache-Control' => 'public, no-store, max-age=3600'
        ], 'No store data');

        $result = $strategy->cache($request, $response);
        $this->assertFalse($result, 'no-store should still prevent caching even with public directive');

        $cached = $strategy->fetch($request);
        $this->assertNull($cached, 'No cache entry should exist when no-store is present');
    }

    /**
     * Test that PrivateCacheStrategy still caches requests with Authorization header normally
     */
    public function testPrivateCacheStrategyAllowsAuthorizationCaching()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new PrivateCacheStrategy($storage);

        $request = new Request('GET', 'https://api.example.com/data', [
            'Authorization' => 'Bearer secret-token'
        ]);
        
        // Private cache should cache this even with just max-age
        $response = new Response(200, [
            'Cache-Control' => 'max-age=3600'
        ], 'Private cache data');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Private cache should allow caching of authenticated requests');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist in private cache for authenticated request');
        $this->assertEquals('Private cache data', (string) $cached->getResponse()->getBody());
    }
}