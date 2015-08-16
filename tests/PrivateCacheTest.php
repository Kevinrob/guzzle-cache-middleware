<?php

namespace Kevinrob\GuzzleCache;


use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Common\Cache\ChainCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\PhpFileCache;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheWrapper;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;

class PrivateCacheTest extends \PHPUnit_Framework_TestCase
{

    public function testCacheProvider()
    {
        $TMP_DIR = __DIR__ . '/tmp/';

        $cacheProviders = [
            new ArrayCache(),
            new ChainCache([new ArrayCache()]),
            new FilesystemCache($TMP_DIR),
            new PhpFileCache($TMP_DIR),
        ];

        $request = new Request('GET', 'test.local');
        $response = new Response(
            200, [
                'Cache-Control' => 'max-age=60'
            ],
            'Test content'
        );

        /** @var CacheProvider $cacheProvider */
        foreach ($cacheProviders as $cacheProvider) {
            $this->rrmdir($TMP_DIR);

            $cache = new PrivateCacheStrategy(
                new DoctrineCacheWrapper($cacheProvider)
            );
            $cache->cache($request, $response);
            $entry = $cache->fetch($request);

            $this->assertNotNull($entry);

            $this->assertEquals(
                (string)$response->getBody(),
                (string)$entry->getResponse()->getBody()
            );
        }

        $this->rrmdir($TMP_DIR);
    }

    /**
     * @param $dir
     *
     * http://stackoverflow.com/a/9760541/244702
     */
    protected function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        $this->rrmdir($dir."/".$object);
                    } else {
                        unlink($dir."/".$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

}