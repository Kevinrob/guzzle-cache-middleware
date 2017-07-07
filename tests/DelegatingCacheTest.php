<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\Delegate\DelegatingCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\NullCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Kevinrob\GuzzleCache\Tests\RequestMatcher\ClosureRequestMatcher;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class DelegatingCacheTest extends TestCase
{

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var HandlerStack
     */
    protected $stack;

    public function setUp()
    {
        // Create default HandlerStack
        $this->stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            return new FulfilledPromise(
                (new Response())
                    ->withAddedHeader('Cache-Control', 'max-age=2')
            );
        });
    }

    public function testDelegate()
    {
        $defaultStrategy = new PublicCacheStrategy();
        $strategy = new DelegatingCacheStrategy($defaultStrategy);
        $strategy->registerRequestMatcher(new ClosureRequestMatcher(function (RequestInterface $request) {
            return 'i-hate-being-cached.com' === $request->getUri()->getHost();
        }), new NullCacheStrategy());

        $this->stack->push(new CacheMiddleware($strategy), 'cache');
        $client = new Client([
            'handler' => $this->stack,
        ]);

        $request = new Request('GET', 'http://i-love-being-cached.com/home');
        $client->send($request);
        $response = $client->send($request);
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        $request = new Request('GET', 'http://i-hate-being-cached.com/home');
        $client->send($request);
        $response = $client->send($request);
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }


}
