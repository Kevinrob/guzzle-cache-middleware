<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Kevinrob\GuzzleCache\CacheMiddleware as BaseCacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheMiddlewareTest extends TestCase
{
    public function testRewindAfterReadingStream()
    {
        $stream = Utils::streamFor('seekable stream');
        $strategy = new PrivateCacheStrategy(
            new Psr6CacheStorage(
                new FilesystemAdapter('', -1, sys_get_temp_dir())
            )
        );
        $request = new Request('GET', '/uri');
        $response = (new Response())->withBody($stream)->withHeader('Cache-Control', 'max-age=3600');

        CacheMiddleware::addToCache(
            $strategy,
            $request,
            $response
        );

        $this->assertEquals('seekable stream', $response->getBody()->getContents());
    }
}

class CacheMiddleware extends BaseCacheMiddleware
{
    public static function addToCache(CacheStrategyInterface $cache, RequestInterface $request, ResponseInterface $response, $update = false)
    {
        return parent::addToCache($cache, $request, $response, $update);
    }
}
