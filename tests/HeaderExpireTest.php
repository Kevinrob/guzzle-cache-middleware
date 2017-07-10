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
 * Date: 29.06.2015
 * Time: 22:48.
 */
class HeaderExpireTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var bool
     */
    protected $sendError = false;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) {
            switch ($request->getUri()->getPath()) {
                case '/expired':
                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader('Expires', gmdate('D, d M Y H:i:s T', time() - 10))
                    );
                case '/2s':
                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader('Expires', gmdate('D, d M Y H:i:s T', time() + 2))
                    );
                case '/stale-if-error':
                    if ($this->sendError) {
                        return new FulfilledPromise(
                            new Response(500)
                        );
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader('Cache-Control', 'stale-if-error=120')
                    );
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testAlreadyExpiredHeader()
    {
        $this->client->get('http://test.com/expired');
        $response = $this->client->get('http://test.com/expired');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testExpiredHeader()
    {
        $this->client->get('http://test.com/2s');

        $response = $this->client->get('http://test.com/2s');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));

        sleep(3);

        $response = $this->client->get('http://test.com/2s');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testStaleIfErrorHeader()
    {
        $this->client->get('http://test.com/stale-if-error');

        sleep(1);

        $this->sendError = true;
        $response = $this->client->get('http://test.com/stale-if-error');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_STALE, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
        $this->sendError = false;
    }
}
