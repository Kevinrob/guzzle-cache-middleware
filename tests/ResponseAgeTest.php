<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\RequestInterface;

class ResponseAgeTest extends \PHPUnit_Framework_TestCase
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
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testAgeHeader()
    {
        $response = $this->client->get('http://test.com/2s');
        $this->assertFalse($response->hasHeader('Age'));

        sleep(1);

        $response = $this->client->get('http://test.com/2s');
        $this->assertEquals(
            CacheMiddleware::HEADER_CACHE_HIT,
            $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO)
        );
        $this->assertGreaterThanOrEqual(1, $response->getHeaderLine('Age'));
    }
}
