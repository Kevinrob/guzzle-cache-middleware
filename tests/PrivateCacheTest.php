<?php

namespace Kevinrob\GuzzleCache\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Storage\Psr16CacheStorage;
use Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class PrivateCacheTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testDstTransitionDoesNotIncorrectlyCache()
    {
        $defaultTz = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            // Set clock to a specific time during the DST transition (Summer -> Winter)
            // Oct 27, 2024 at 02:00:56 CEST
            $transitionTime = new \DateTimeImmutable('2024-10-27 02:00:56', new \DateTimeZone('Europe/Berlin'));
            $mockClock = new \Symfony\Component\Clock\MockClock($transitionTime);
            \Kevinrob\GuzzleCache\Clock::set($mockClock);

            $request = new Request('GET', 'test.local');
            $response = new Response(
                200, [
                    'Cache-Control' => 'no-cache', // This forces a -1 second expiration
                    'Etag' => '"dummy"', // Required to allow caching of no-cache responses
                ],
                'Test content'
            );

            $cache = new PrivateCacheStrategy(
                new VolatileRuntimeStorage()
            );

            // Cache the response
            $cache->cache($request, $response);

            // Fetch the cached entry
            $entry = $cache->fetch($request);

            // It should not be null, but it should be STALE (expired)
            $this->assertNotNull($entry, 'The entry should be stored in cache.');
            $this->assertTrue($entry->isStale(), 'The entry should be stale (expired) even during DST transition.');
            $this->assertGreaterThan(0, $entry->getStaleAge(), 'The stale age should be positive.');
        } finally {
            date_default_timezone_set($defaultTz);
            \Kevinrob\GuzzleCache\Clock::set(new \Symfony\Component\Clock\NativeClock()); // Reset to real-time clock
        }
    }

    /**
     * @param CacheStorageInterface $cacheProvider
     * @param $TMP_DIR
     * @return void
     * @dataProvider cacheProvider
     */
    public function testCacheProvider(CacheStorageInterface $cacheProvider, $TMP_DIR = null)
    {
        $request = new Request('GET', 'test.local');
        $response = new Response(
            200, [
                'Cache-Control' => 'max-age=60',
            ],
            'Test content'
        );
        $response2 = new Response(
            200, [
            'Cache-Control' => 'max-age=90',
        ],
            'Test new content'
        );

        if ($TMP_DIR !== null) {
            $this->rrmdir($TMP_DIR);
        }

        $cache = new PrivateCacheStrategy(
            $cacheProvider
        );
        $cache->cache($request, $response);
        $entry = $cache->fetch($request);

        $this->assertNotNull($entry, get_class($cacheProvider));
        $this->assertEquals(
            (string) $response->getBody(),
            (string) $entry->getResponse()->getBody(),
            get_class($cacheProvider)
        );

        $cache->update($request, $response2);
        $entry = $cache->fetch($request);

        $this->assertNotNull($entry, get_class($cacheProvider));
        $this->assertEquals(
            (string) $response2->getBody(),
            (string) $entry->getResponse()->getBody(),
            get_class($cacheProvider)
        );

        $cache->delete($request);
        $entry = $cache->fetch($request);
        $this->assertNull($entry, get_class($cacheProvider));

        if ($TMP_DIR !== null) {
            $this->rrmdir($TMP_DIR);
        }
    }

    public function cacheProvider()
    {
        $TMP_DIR = sys_get_temp_dir().'/guzzle-cache-tests-private-'.uniqid().'/';
        if (!is_dir($TMP_DIR)) {
            mkdir($TMP_DIR, 0755, true);
        }
        return [
            'flysystem' => [ new FlysystemStorage(new LocalFilesystemAdapter($TMP_DIR)), $TMP_DIR ],
            'psr6' => [ new Psr6CacheStorage(new ArrayCachePool()) ],
            'psr16' => [ new Psr16CacheStorage(new SimpleCacheBridge(new ArrayCachePool())) ],
            'volatileruntimeStorage' => [ new VolatileRuntimeStorage() ]
        ];
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
