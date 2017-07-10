<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\RequestInterface;

class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            if ($request->getUri()->getPath() === '/no-seek') {
                return new FulfilledPromise(
                    (new Response())
                        ->withBody(new NoSeekStream(\GuzzleHttp\Psr7\stream_for('I am not seekable!')))
                        ->withHeader('Expires', gmdate('D, d M Y H:i:s T', time() + 120))
                );
            }

            return new FulfilledPromise(
                (new Response())
                    ->withBody(\GuzzleHttp\Psr7\stream_for('Hello world!'))
                    ->withHeader('Expires', gmdate('D, d M Y H:i:s T', time() + 120))
            );
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testNoBreakClient()
    {
        $response = $this->client->get('anything');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello world!', $response->getBody());
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        $response = $this->client->get('anything');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello world!', $response->getBody());
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testNoCacheOtherMethod()
    {
        $this->client->post('anything');
        $response = $this->client->post('anything');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        $this->client->put('anything');
        $response = $this->client->put('anything');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        $this->client->delete('anything');
        $response = $this->client->delete('anything');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        $this->client->patch('anything');
        $response = $this->client->patch('anything');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testMultipleHit()
    {
        $response = $this->client->get('/no-seek');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertEquals('I am not seekable!', $response->getBody()->getContents());

        $response = $this->client->get('/no-seek');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertEquals('I am not seekable!', $response->getBody()->getContents());
    }
}
