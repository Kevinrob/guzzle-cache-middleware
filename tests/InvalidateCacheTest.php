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
        $stack = HandlerStack::create(function () {
            return new FulfilledPromise(new Response(200, [
                'Cache-Control' => 'private, max-age=300'
            ]));
        });

        $this->middelware = new CacheMiddleware(new PrivateCacheStrategy(
            new Psr6CacheStorage(new ArrayCachePool()),
        ));

        $stack->push($this->middelware, 'cache');

        $this->client = new Client(['handler' => $stack]);
    }

    /**
     * @dataProvider unsafeMethods
     */
    public function testItInvalidatesForUnsafeHttpMethods($unsafeMethod)
    {
        $this->middelware->setHttpMethods([
            'GET' => true,
            'HEAD' => true,
        ]);

        $this->client->get('resource');
        $this->client->head('resource');

        $response = $this->client->{$unsafeMethod}('resource');
        $this->assertSame('1', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->get('resource');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine('X-Kevinrob-Cache'));

        $response = $this->client->head('resource');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine('X-Kevinrob-Cache'));
    }

    /**
     * @dataProvider safeMethods
     */
    public function testItDoesInvalidatesForSafeHttpMethods($safeMethod)
    {
        $this->client->get('resource');

        $response = $this->client->{$safeMethod}('resource');
        $this->assertSame('', $response->getHeaderLine(CacheMiddleware::HEADER_INVALIDATION));

        $response = $this->client->get('resource');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine('X-Kevinrob-Cache'));
    }

    public function unsafeMethods()
    {
        return [
            'delete' => ['delete'],
            'put' => ['put'],
            'post' => ['post'],
        ];
    }

    public function safemethods()
    {
        return [
            'get' => ['get'],
            'options' => ['options'],
            'trace' => ['trace'],
            'head' => ['head'],
        ];
    }
}
