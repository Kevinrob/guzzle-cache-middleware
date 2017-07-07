<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\RequestInterface;

class ResponseCacheControlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            switch ($request->getUri()->getPath()) {
                case '/2s':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'max-age=2')
                    );
                case '/2s-complex':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'invalid-token="yes", max-age=2, stale-while-revalidate=60')
                    );
                case '/no-store':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'no-store')
                    );
                case '/no-cache':
                    if ($request->getHeaderLine('If-None-Match') == 'TheHash') {
                        return new FulfilledPromise(new Response(304));
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'no-cache')
                            ->withAddedHeader('Etag', 'TheHash')
                    );
                case '/2s-expires':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'public')
                            ->withAddedHeader('Expires', gmdate('D, d M Y H:i:s T', time() + 2))
                    );
              case '/no-headers':
                    return new FulfilledPromise(
                      (new Response())
                    );
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testMaxAgeHeader()
    {
        $this->client->get('http://test.com/2s');

        $response = $this->client->get('http://test.com/2s');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        sleep(3);

        $response = $this->client->get('http://test.com/2s');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testMaxAgeComplexHeader()
    {
        $this->client->get('http://test.com/2s-complex');

        $response = $this->client->get('http://test.com/2s-complex');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        sleep(3);

        $response = $this->client->get('http://test.com/2s-complex');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testNoStoreHeader()
    {
        $this->client->get('http://test.com/no-store');

        $response = $this->client->get('http://test.com/no-store');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testNoCacheHeader()
    {
        $this->client->get('http://test.com/no-cache');

        $response = $this->client->get('http://test.com/no-cache');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testWithExpires()
    {
        $this->client->get('http://test.com/2s-expires');

        $response = $this->client->get('http://test.com/2s-expires');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        sleep(3);

        $response = $this->client->get('http://test.com/2s-expires');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    /**
     * Test responses with no caching headers.
     *
     * When neither a Cache-Control or Expires header is set, caching behaviour
     * is undefined as per section 13.4 in RFC2616. The current behaviour is to
     * not cache those requests.
     *
     * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html
     */
    public function testWithMissingCacheHeaders()
    {
        $this->client->get('http://test.com/no-headers');
        $response = $this->client->get('http://test.com/no-headers');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

}
