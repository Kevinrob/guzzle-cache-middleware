<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Psr\Http\Message\RequestInterface;

class GreedyCacheTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    private $client;

    protected function setUp()
    {
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            switch ($request->getUri()->getPath()) {
                case '/vary':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Vary', '*')
                    );
                case '/no-store':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'no-store')
                    );
                case '/no-cache':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'no-cache')
                    );
                case '/pragma':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Pragma', 'no-cache')
                    );
                case '/partial-content':
                    return new FulfilledPromise(
                        (new Response())
                            ->withStatus(206)
                    );
                case '/with-etag':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Etag', 'the-etag')
                    );
            }

            throw new \InvalidArgumentException();
        });

        $stack->push(new CacheMiddleware(
                new GreedyCacheStrategy(null, 10))
        );

        $this->client = new Client(['handler' => $stack]);
    }

    /**
     * @param Request $request
     *
     * @dataProvider cachableRequestProvider
     */
    public function testItIsGreedy(Request $request)
    {
        $this->client->send($request); // Will be cached
        $response = $this->client->send($request); // Will come from cache

        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertTrue($response->hasHeader('Warning'));
    }

    /**
     * @param Request $request
     *
     * @dataProvider nonCachableRequestProvider
     */
    public function testItIsNotStupid(Request $request)
    {
        $this->client->send($request); // Will not be cached
        $response = $this->client->send($request); // Will not come from cache

        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    /**
     * @param Request $requestA
     * @param Request $requestB
     * @param Request $requestC
     * @param Request $requestD
     *
     * @dataProvider varyingCachableRequestProvider
     */
    public function testItCanHandleArbitraryVaryingHeaders(Request $requestA, Request $requestB, Request $requestC, Request $requestD)
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(),
            new Response(),
            new Response(),
            new Response(),
        ]));

        $stack->push(new CacheMiddleware(
                new GreedyCacheStrategy(
                    null,
                    10,
                    new KeyValueHttpHeader(['Authorization'])
                ))
        );

        $this->client = new Client([
            'handler' => $stack
        ]);

        $responseA = $this->client->send($requestA);
        $responseB = $this->client->send($requestB);
        $responseC = $this->client->send($requestC);
        $responseD = $this->client->send($requestD);

        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $responseA->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $responseB->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $responseC->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $responseD->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

    }

    public function cachableRequestProvider()
    {
        return [
            [new Request('GET', '/vary')],
            [new Request('GET', '/no-store')],
            [new Request('GET', '/no-cache')],
            [new Request('GET', '/pragma')],
            [new Request('GET', '/with-etag')],
        ];
    }

    public function nonCachableRequestProvider()
    {
        return [
            [new Request('POST', '/vary')],
            [new Request('GET', '/partial-content')],
        ];
    }

    public function varyingCachableRequestProvider()
    {
        return [
            [
                'A' => new Request('GET', '/something', ['Authorization' => 'Bearer foo']), // Should not be cached
                'B' => new Request('GET', '/something', ['Authorization' => 'Bearer bar']), // Same URI but different authorization - should not be cached
                'C' => new Request('GET', '/something', ['Authorization' => 'Bearer foo']), // Same authorization token as request A - should be cached
                'D' => new Request('GET', '/some_other_thing', ['Authorization' => 'Bearer foo']), // Same authorization token as request A and C, but different URI - should not be cached
            ]
        ];
    }
}
