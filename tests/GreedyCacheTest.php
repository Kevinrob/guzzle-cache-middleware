<?php

namespace Kevinrob\GuzzleCache;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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

    public function cachableRequestProvider()
    {
        return [
            [new Request('GET', '/vary')],
            [new Request('GET', '/no-store')],
            [new Request('GET', '/no-cache')],
            [new Request('GET', '/pragma')],
        ];
    }

    public function nonCachableRequestProvider()
    {
        return [
            [new Request('POST', '/vary')],
            [new Request('GET', '/partial-content')],
        ];
    }
}
