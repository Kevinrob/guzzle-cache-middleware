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
class ValidationTest extends \PHPUnit_Framework_TestCase
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
                case '/etag':
                    if ($request->getHeaderLine("If-None-Match") == 'MyBeautifulHash') {
                        return new FulfilledPromise(new Response(304));
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader("Etag", 'MyBeautifulHash')
                    );
                case '/etag-changed':
                    if ($request->getHeaderLine("If-None-Match") == 'MyBeautifulHash') {
                        return new FulfilledPromise(
                            (new Response())
                                ->withHeader("Etag", 'MyBeautifulHash2')
                        );
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader("Etag", 'MyBeautifulHash')
                    );
                case '/stale-while-revalidate':
                    if ($request->getHeaderLine("If-None-Match") == 'MyBeautifulHash') {
                        return new FulfilledPromise(
                            (new Response(304))
                                ->withHeader("Cache-Control", 'max-age=10')
                        );
                    }

                    return new FulfilledPromise(
                        (new Response())
                            ->withHeader("Etag", 'MyBeautifulHash')
                            ->withHeader("Cache-Control", 'max-age=1')
                            ->withAddedHeader("Cache-Control", 'stale-while-revalidate=60')
                    );
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(CacheMiddleware::getMiddleware(), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
        CacheMiddleware::setClient($this->client);
    }

    public function testEtagHeader()
    {
        $this->client->get("http://test.com/etag");

        sleep(1);

        $response = $this->client->get("http://test.com/etag");
        $this->assertEquals("HIT with validation", $response->getHeaderLine("X-Cache"));
    }

    public function testEtagChangeHeader()
    {
        $this->client->get("http://test.com/etag-changed");

        sleep(1);

        $response = $this->client->get("http://test.com/etag-changed");
        $this->assertEquals("", $response->getHeaderLine("X-Cache"));
    }

    public function testStaleWhileRevalidateHeader()
    {
        $this->client->get("http://test.com/stale-while-revalidate");

        sleep(2);

        $response = $this->client->get("http://test.com/stale-while-revalidate");
        $this->assertEquals("Stale while revalidate", $response->getHeaderLine("X-Cache"));

        // Do that at the end of the php script...
        CacheMiddleware::purgeReValidation();

        $response = $this->client->get("http://test.com/stale-while-revalidate");
        $this->assertEquals("HIT", $response->getHeaderLine("X-Cache"));
    }

}