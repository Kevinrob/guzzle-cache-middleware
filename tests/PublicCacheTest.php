<?php

namespace Kevinrob\GuzzleCache;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\ChainCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\PhpFileCache;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheWrapper;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Psr\Http\Message\RequestInterface;

class PublicCacheTest extends \PHPUnit_Framework_TestCase
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
                case '/private':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'private,max-age=2')
                    );
                case '/max-age':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 'max-age=2')
                    );
                case '/s-maxage':
                    return new FulfilledPromise(
                        (new Response())
                            ->withAddedHeader('Cache-Control', 's-maxage=2,max-age=0')
                    );
            }

            throw new \InvalidArgumentException();
        });

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(
            new PublicCacheStrategy()
        ), 'cache');

        // Initialize the client with the handler option
        $this->client = new Client(['handler' => $stack]);
    }

    public function testNoCachePrivate()
    {
        $this->client->get('http://test.com/private');

        $response = $this->client->get('http://test.com/private');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_MISS, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testCacheMaxAge()
    {
        $this->client->get('http://test.com/max-age');

        $response = $this->client->get('http://test.com/max-age');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testCacheSMaxAge()
    {
        $this->client->get('http://test.com/s-maxage');

        $response = $this->client->get('http://test.com/s-maxage');
        $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
    }

    public function testCacheProvider()
    {
        $TMP_DIR = __DIR__.'/tmp/';

        $cacheProviders = [
            new ArrayCache(),
            new ChainCache([new ArrayCache()]),
            new FilesystemCache($TMP_DIR),
            new PhpFileCache($TMP_DIR),
        ];

        $request = new Request('GET', 'test.local');
        $response = new Response(
            200, [
                'Cache-Control' => 's-maxage=60',
            ],
            'Test content'
        );

        /** @var CacheProvider $cacheProvider */
        foreach ($cacheProviders as $cacheProvider) {
            $this->rrmdir($TMP_DIR);

            $cache = new PublicCacheStrategy(
                new DoctrineCacheWrapper($cacheProvider)
            );
            $cache->cache($request, $response);
            $entry = $cache->fetch($request);

            $this->assertNotNull($entry);

            $this->assertEquals(
                (string) $response->getBody(),
                (string) $entry->getResponse()->getBody()
            );
        }

        $this->rrmdir($TMP_DIR);
    }

    /**
     * @param $dir
     *
     * http://stackoverflow.com/a/9760541/244702
     */
    protected function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        $this->rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
