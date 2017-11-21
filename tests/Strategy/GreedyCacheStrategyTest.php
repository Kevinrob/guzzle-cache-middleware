<?php

namespace Kevinrob\GuzzleCache\Strategy;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage;
use Psr\Http\Message\RequestInterface;

class GreedyCacheStrategyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            return new FulfilledPromise((new Response()));
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(
            new GreedyCacheStrategy(new VolatileRuntimeStorage(), 2)
        ), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testDefaultTtl()
    {
        $this->client->get('http://test.com/');

        $response = $this->client->get('http://test.com/');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        sleep(3);

        $response = $this->client->get('http://test.com/');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testHeaderTtl()
    {
        /** @var Request $request */
        $request = (new Request('GET', 'http://test.com/2'))
            ->withHeader(GreedyCacheStrategy::HEADER_TTL, -2);
        $this->client->send($request);

        $response = $this->client->send($request);
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        /** @var Request $request */
        $request = (new Request('GET', 'http://test.com/2'))
            ->withHeader(GreedyCacheStrategy::HEADER_TTL, 5);

        $this->client->send($request);

        $response = $this->client->send($request);
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        sleep(6);

        $response = $this->client->send($request);
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }
}
