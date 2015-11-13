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
}
