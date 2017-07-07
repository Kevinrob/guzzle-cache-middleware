<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\NullCacheStrategy;
use PHPUnit\Framework\TestCase;

class NullCacheTest extends TestCase
{

    public function testCacheIsNeverHit()
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(),
            new Response(),
        ]));

        $stack->push(new CacheMiddleware(new NullCacheStrategy()));

        $request = new Request('GET', '/foo');

        $client = new Client([
            'handler' => $stack
        ]);
        $client->send($request); // Will not be cached
        $response = $client->send($request); // Will not come from cache

        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

}
