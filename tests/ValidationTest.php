<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 30.06.2015
 * Time: 12:58.
 */
class ValidationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var CacheMiddleware
     */
    protected $cache;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            switch ($request->getUri()->getPath()) {
                case '/etag':
                    if ($request->getHeaderLine('If-None-Match') == 'MyBeautifulHash') {
                        return new FulfilledPromise(
                            (new Response(304))
                                ->withHeader('X-Replaced', '2')
                        );
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader('Etag', 'MyBeautifulHash')
                            ->withHeader('X-Base-Info', '1')
                            ->withHeader('X-Replaced', '1')
                    );
                case '/etag-changed':
                    if ($request->getHeaderLine('If-None-Match') == 'MyBeautifulHash') {
                        return new FulfilledPromise(
                            (new Response())
                                ->withHeader('Etag', 'MyBeautifulHash2')
                        );
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader('Etag', 'MyBeautifulHash')
                    );
                case '/stale-while-revalidate':
                    if ($request->getHeaderLine('If-None-Match') == 'MyBeautifulHash') {
                        return new FulfilledPromise(
                            (new Response(304))
                                ->withHeader('Cache-Control', 'max-age=10')
                        );
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader('Etag', 'MyBeautifulHash')
                            ->withHeader('Cache-Control', 'max-age=1')
                            ->withAddedHeader('Cache-Control', 'stale-while-revalidate=60')
                    );
            }

            throw new \InvalidArgumentException();
        });

        $this->cache = new CacheMiddleware();

        // Add this middleware to the top with `push`
        $stack->push($this->cache, 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
        $this->cache->setClient($this->client);
    }

    public function testEtagHeader()
    {
        $response = $this->client->get('http://test.com/etag');
        $this->assertEquals('1', $response->getHeaderLine('X-Base-Info'));
        $this->assertEquals('1', $response->getHeaderLine('X-Replaced'));

        sleep(1);

        $response = $this->client->get('http://test.com/etag');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertEquals('1', $response->getHeaderLine('X-Base-Info'));
        $this->assertEquals('2', $response->getHeaderLine('X-Replaced'));
    }

    public function testEtagChangeHeader()
    {
        $this->client->get('http://test.com/etag-changed');

        sleep(1);

        $response = $this->client->get('http://test.com/etag-changed');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testStaleWhileRevalidateHeader()
    {
        $this->client->get('http://test.com/stale-while-revalidate');

        sleep(2);

        $response = $this->client->get('http://test.com/stale-while-revalidate');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_STALE, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        // Do that at the end of the php script...
        $this->cache->purgeReValidation();

        $response = $this->client->get('http://test.com/stale-while-revalidate');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }
}
