<?php
/**
 * Created by PhpStorm.
 * User: Kevin
 * Date: 30.06.2015
 * Time: 11:58
 */

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function(RequestInterface $request, array $options) {
            return new FulfilledPromise(
                (new Response())
                    ->withBody(\GuzzleHttp\Psr7\stream_for('Hello world!'))
            );
        });

        // Add this middleware to the top with `push`
        $stack->push(CacheMiddleware::getMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testNoBreakClient()
    {
        $response = $this->client->get("anything");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello world!', $response->getBody());
    }

}