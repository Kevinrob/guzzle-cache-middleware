<?php

namespace Kevinrob\GuzzleCache\Tests\Strategy;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage;
use Psr\Http\Message\RequestInterface;
use PHPUnit\Framework\TestCase;

class GreedyCacheStrategyTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    protected function setUp(): void
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

    public function testGreedyCacheStrategyDeletion()
    {
        $storage = new VolatileRuntimeStorage();
        $strategy = new GreedyCacheStrategy($storage, 60);

        $request = new Request('GET', 'http://test.com/');
        $response = new Response(200, [], 'Test content');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Response should be cached');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist');
        $this->assertEquals('Test content', (string) $cached->getResponse()->getBody());

        $deleted = $strategy->delete($request);
        $this->assertTrue($deleted, 'Cache entry should be deleted successfully');

        $cached = $strategy->fetch($request);
        $this->assertNull($cached, 'Cache entry should no longer exist after deletion');
    }

    public function testGreedyDeletionWithVaryHeaders()
    {
        $storage = new VolatileRuntimeStorage();
        $varyHeaders = new KeyValueHttpHeader(['Authorization']);
        $strategy = new GreedyCacheStrategy($storage, 60, $varyHeaders);

        $request = new Request('GET', 'http://test.com/', [
            'Authorization' => 'Bearer token123'
        ]);
        $response = new Response(200, [], 'Authorized content');

        $result = $strategy->cache($request, $response);
        $this->assertTrue($result, 'Response should be cached with vary headers');

        $cached = $strategy->fetch($request);
        $this->assertNotNull($cached, 'Cache entry should exist with vary headers');
        $this->assertEquals('Authorized content', (string) $cached->getResponse()->getBody());

        $deleted = $strategy->delete($request);
        $this->assertTrue($deleted, 'Cache entry should be deleted successfully with vary headers');

        $cached = $strategy->fetch($request);
        $this->assertNull($cached, 'Cache entry should no longer exist after deletion with vary headers');
    }
}
