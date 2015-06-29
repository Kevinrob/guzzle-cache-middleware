<?php
namespace Kevinrob\GuzzleCache;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 29.06.2015
 * Time: 22:48
 */
class HeaderExpireTest extends \PHPUnit_Framework_TestCase
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
                    ->withHeader("Expires", gmdate('D, d M Y H:i:s T', time() + 2))
            );
        });

        // Add this middleware to the top with `push`
        $stack->push(CacheMiddleware::getMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testMockedResponse()
    {
        $response = $this->client->get("anything");

        $this->assertEquals(200, $response->getStatusCode());
    }

}