<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class SinkTest extends TestCase
{

    /**
     * @runInSeparateProcess
     */
    public function testSinkWithCacheHit()
    {
        $mock = new MockHandler([
            new Response(200, ['Cache-Control' => 'max-age=60'], 'Hello Sink!')
        ]);
        $stack = HandlerStack::create($mock);

        $cacheDir = sys_get_temp_dir() . '/guzzle-cache-' . uniqid();
        if (!mkdir($cacheDir) && !is_dir($cacheDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $cacheDir));
        }

        try {
            $storage = new FlysystemStorage(new LocalFilesystemAdapter($cacheDir));
            $stack->push(new CacheMiddleware(new PrivateCacheStrategy($storage)), 'cache');

            $client = new Client(['handler' => $stack]);

            // 1. Test with string path
            $sink1 = tempnam(sys_get_temp_dir(), 'guzzle-sink-1');
            try {
                $client->get('http://test.com/sink', ['sink' => $sink1]);
                $this->assertEquals('Hello Sink!', file_get_contents($sink1));

                $sink2 = tempnam(sys_get_temp_dir(), 'guzzle-sink-2');
                try {
                    $response = $client->get('http://test.com/sink', ['sink' => $sink2]);
                    $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
                    $this->assertEquals('Hello Sink!', file_get_contents($sink2));
                } finally {
                    @unlink($sink2);
                }
            } finally {
                @unlink($sink1);
            }

            // 2. Test with resource
            $resourceSink = fopen('php://temp', 'r+');
            try {
                $response = $client->get('http://test.com/sink', ['sink' => $resourceSink]);
                $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
                rewind($resourceSink);
                $this->assertEquals('Hello Sink!', stream_get_contents($resourceSink));
            } finally {
                @fclose($resourceSink);
            }

            // 3. Test with StreamInterface
            $streamSink = \GuzzleHttp\Psr7\Utils::streamFor(fopen('php://temp', 'r+'));
            try {
                $response = $client->get('http://test.com/sink', ['sink' => $streamSink]);
                $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
                $streamSink->rewind();
                $this->assertEquals('Hello Sink!', $streamSink->getContents());
            } finally {
                $streamSink->close();
            }
        } finally {
            $this->rrmdir($cacheDir);
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testSinkWithRevalidation304()
    {
        $mock = new MockHandler([
            new Response(200, [
                'Cache-Control' => 'max-age=1',
                'Etag' => '"abc"'
            ], 'Hello Revalidation!'),
            new Response(304, [
                'Cache-Control' => 'max-age=60',
                'Etag' => '"abc"'
            ])
        ]);
        $stack = HandlerStack::create($mock);

        try {
            $storage = new \Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage();
            $stack->push(new CacheMiddleware(new PrivateCacheStrategy($storage)), 'cache');

            $client = new Client(['handler' => $stack]);

            // 1. Populate cache
            $sink1 = tempnam(sys_get_temp_dir(), 'guzzle-sink-reval-1');
            try {
                $client->get('http://test.com/reval', ['sink' => $sink1]);
                $this->assertEquals('Hello Revalidation!', file_get_contents($sink1));

                // 2. Wait for expiration using mock clock
                $mockClock = new \Symfony\Component\Clock\MockClock(new \DateTimeImmutable('+2 seconds'));
                \Kevinrob\GuzzleCache\Clock::set($mockClock);

                $sink2 = tempnam(sys_get_temp_dir(), 'guzzle-sink-reval-2');
                try {
                    $response = $client->get('http://test.com/reval', ['sink' => $sink2]);
                    $this->assertEquals(CacheMiddleware::HEADER_CACHE_HIT, $response->getHeaderLine(CacheMiddleware::HEADER_CACHE_INFO));
                    $this->assertEquals('Hello Revalidation!', file_get_contents($sink2), 'Sink should be populated after 304 revalidation');
                } finally {
                    @unlink($sink2);
                }
            } finally {
                @unlink($sink1);
            }
        } finally {
            // No cleanup needed
        }
    }

    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                        $this->rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                    else
                        unlink($dir. DIRECTORY_SEPARATOR .$object);
                }
            }
            rmdir($dir);
        }
    }
}
