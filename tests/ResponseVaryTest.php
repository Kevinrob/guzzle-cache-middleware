<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
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

    public function testVaryAllHeaderDoesNotCache()
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

    public function testVaryHeadersHaveUniqueCache()
    {
        $headerSetA = [
            'My-Header' => 'hello'
        ];

        $headerSetB = [
            'My-Header' => 'goodbye'
        ];

        $this->client->get('http://test.com/vary-my-header', [
            'headers' => $headerSetA
        ]);

        // Second request with A headers - cache hit
        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => $headerSetA
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_HIT,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );

        // First request with B headers - cache miss
        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => $headerSetB
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_MISS,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );

        // Second request with B headers - cache hit
        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => $headerSetB
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_HIT,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );

        // Third request with A headers - cache hit (make sure B cache did not overwrite A cache)
        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => $headerSetA
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_HIT,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );
    }

    public function testVaryHeaderCaseChangeDoesNotCauseCacheMiss()
    {
        $this->client->get('http://test.com/vary-my-header', [
            'headers' => [
                'My-Header' => 'hello'
            ]
        ]);

        $response = $this->client->get('http://test.com/vary-my-header', [
            'headers' => [
                'my-header' => 'hello'
            ]
        ]);
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_HIT,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );
    }
}
