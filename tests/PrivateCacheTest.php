<?php

namespace Kevinrob\GuzzleCache\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\ChainCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\PhpFileCache;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\Storage\CompressedDoctrineCacheStorage;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\Flysystem\Adapter\Local;

class PrivateCacheTest extends \PHPUnit_Framework_TestCase
{
    public function testCacheProvider()
    {
        $TMP_DIR = __DIR__.'/tmp/';

        $cacheProviders = [
            new DoctrineCacheStorage(new ArrayCache()),
            new DoctrineCacheStorage(new ChainCache([new ArrayCache()])),
            new DoctrineCacheStorage(new FilesystemCache($TMP_DIR)),
            new DoctrineCacheStorage(new PhpFileCache($TMP_DIR)),
            new FlysystemStorage(new Local($TMP_DIR)),
            new Psr6CacheStorage(new ArrayCachePool()),
            new CompressedDoctrineCacheStorage(new ArrayCache()),
        ];

        $request = new Request('GET', 'test.local');
        $response = new Response(
            200, [
                'Cache-Control' => 'max-age=60',
            ],
            'Test content'
        );

        /** @var CacheProvider $cacheProvider */
        foreach ($cacheProviders as $cacheProvider) {
            $this->rrmdir($TMP_DIR);

            $cache = new PrivateCacheStrategy(
                $cacheProvider
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
