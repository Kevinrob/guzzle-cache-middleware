<?php

namespace Kevinrob\GuzzleCache;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class ResponseVaryTest extends \PHPUnit_Framework_TestCase
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
                case '/vary-all':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'max-age=2')
                            ->withAddedHeader('Vary', '*')
                    );
                case '/vary-my-header':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'max-age=2')
                            ->withAddedHeader('Vary', 'My-Header, Absent-Header')
                    );
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testVaryAllHeader()
    {
        $this->client->get('http://test.com/vary-all');

        $response = $this->client->get('http://test.com/vary-all');
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_MISS,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );
    }

    public function testVaryHeader()
    {
        $this->client->get('http://test.com/vary-my-header', [
            'headers' => [
                'My-Header' => 'hello',
            ],
        ]);

        // Same vary headers
        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => [
                'My-Header' => 'hello',
            ],
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_HIT,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );

        // Add a previously absent header
        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => [
                'My-Header' => 'hello',
                'Absent-Header' => 'present',
            ],
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_MISS,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );

        // Without any headers
        $response = $this->client->get('http://test.com/vary-my-header');
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_MISS,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );
    }
}
