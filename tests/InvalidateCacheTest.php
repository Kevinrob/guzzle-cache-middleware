<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use PHPUnit\Framework\TestCase;

class InvalidateCacheTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function () {
            return new FulfilledPromise(new Response());
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

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
}
