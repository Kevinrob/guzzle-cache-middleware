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
 * Date: 30.06.2015
 * Time: 12:58
 */
class HeaderCacheControlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public function setUp()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create(function(RequestInterface $request, array $options) {
            switch ($request->getUri()->getPath()) {
                case '/2s':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader("Cache-Control", "max-age=2")
                    );
                case '/no-store':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader("Cache-Control", "no-store")
                    );
                case '/no-cache':
                    if ($request->getHeaderLine("If-None-Match") == "TheHash") {
                        return new FulfilledPromise(new Response(304));
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader("Cache-Control", "no-cache")
                            ->withAddedHeader("Etag", "TheHash")
                    );
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testMaxAgeHeader()
    {
        $this->client->get("http://test.com/2s");

        $response = $this->client->get("http://test.com/2s");
        $this->assertEquals("HIT", $response->getHeaderLine("X-Cache"));

        sleep(3);

        $response = $this->client->get("http://test.com/2s");
        $this->assertEquals("", $response->getHeaderLine("X-Cache"));
    }

    public function testNoStoreHeader()
    {
        $this->client->get("http://test.com/no-store");

        $response = $this->client->get("http://test.com/no-store");
        $this->assertEquals("", $response->getHeaderLine("X-Cache"));
    }

    public function testNoCacheHeader()
    {
        $this->client->get("http://test.com/no-cache");

        $response = $this->client->get("http://test.com/no-cache");
        $this->assertEquals("HIT with validation", $response->getHeaderLine("X-Cache"));
    }

}