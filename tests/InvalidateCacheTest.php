<?php

namespace Kevinrob\GuzzleCache\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use PHPUnit\Framework\TestCase;

class InvalidateCacheTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var CacheMiddleware
     */
    protected $middelware;

    protected function setUp(): void
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function () {
            return new FulfilledPromise(new Response(200, [
                'Cache-Control' => 'private, max-age=300'
            ]));
        });

        $this->middelware = new CacheMiddleware(new PrivateCacheStrategy(
            new Psr6CacheStorage(new ArrayCachePool()),
        ));

        // Add this middleware to the top with `push`
        $stack->push($this->middelware, 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testInvalidationCacheIfNotValidHttpMethod()
    {
        $response = $this->client->get('anything');
        $this->assertSame('', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->post('anything');
        $this->assertSame('1', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->put('anything');
        $this->assertSame('1', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->delete('anything');
        $this->assertSame('1', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->patch('anything');
        $this->assertSame('1', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));
    }

    public function testItInvalidatesForAllHttpMethods()
    {
        $this->middelware->setHttpMethods([
            'GET' => true,
            'POST' => true,
        ]);

        $this->client->get('anything');
        $this->client->post('anything');

        $response = $this->client->delete('anything');
        $this->assertSame('1', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->get('anything');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine('X-Kevinrob-Cache'));
        $response = $this->client->post('anything');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine('X-Kevinrob-Cache'));
    }
}
