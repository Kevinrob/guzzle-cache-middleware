<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheEntry;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @group time-sensitive
 */
class CacheEntryTest extends TestCase
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ResponseInterface
     */
    private $response;

    private $responseHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $this->response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $this->response->method('getHeader')->will($this->returnCallback(function ($header) {
            if (isset($this->responseHeaders[$header])) {
                return $this->responseHeaders[$header];
            }

            return [];
        }));
        $this->response->method('hasHeader')->will($this->returnCallback(function ($header) {
            return isset($this->responseHeaders[$header]);
        }));
    }

    public function testTtlForValidateableResponseShouldBeInfinite()
    {
        $this->setResponseHeader('Etag', 'some-etag');
        $cacheEntry = new CacheEntry($this->request, $this->response, $this->makeDateTimeOffset());

        // getTTL() will return 0 to indicate "infinite"
        $this->assertEquals(0, $cacheEntry->getTTL());
    }

    public function testTtlForSimpleExpiration()
    {
        $cacheEntry = new CacheEntry($this->request, $this->response, $this->makeDateTimeOffset(10));

        $this->assertEquals(10, $cacheEntry->getTTL());
    }

    public function testTtlConsidersStaleIfError()
    {
        $this->setResponseHeader('Cache-Control', 'stale-if-error=30');
        $cacheEntry = new CacheEntry($this->request, $this->response, $this->makeDateTimeOffset(10));

        $this->assertEquals(40, $cacheEntry->getTTL());
    }

    public function testTtlConsidersStaleWhileRevalidate()
    {
        $this->setResponseHeader('Cache-Control', 'stale-while-revalidate=30');
        $cacheEntry = new CacheEntry($this->request, $this->response, $this->makeDateTimeOffset(10));

        $this->assertEquals(40, $cacheEntry->getTTL());
    }

    public function testTtlUsesMaximumPossibleLifetime()
    {
        $this->setResponseHeader('Cache-Control', 'stale-while-revalidate=30, stale-if-error=60');
        $cacheEntry = new CacheEntry($this->request, $this->response, $this->makeDateTimeOffset(10));

        $this->assertEquals(70, $cacheEntry->getTTL());
    }

    public function testCacheEntryShouldBeSerializableWithIgBinaryWithoutWarning()
    {
        $request = new Request(
            'GET',
            'test.local',
            [],
            'Sample body' // Always include a body in the request to be sure there is a stream in it
        );
        $response = new Response(
            200, [
            'Cache-Control' => 'max-age=60',
        ],
            'Test content'
        );
        $cacheEntry = new CacheEntry($request, $response, $this->makeDateTimeOffset(10));

        if(extension_loaded('igbinary')) {
            /**
             * @var CacheEntry $cacheEntryPostDeserialization
             */
            $cacheEntryPostDeserialization = igbinary_unserialize(igbinary_serialize($cacheEntry));
            $this->assertEquals((string)$cacheEntry->getOriginalRequest()->getBody(), (string)$cacheEntryPostDeserialization->getOriginalRequest()->getBody());
            $this->assertEquals((string)$cacheEntry->getOriginalResponse()->getBody(), (string)$cacheEntryPostDeserialization->getOriginalResponse()->getBody());
        } else {
            $this->addWarning('Extension igbinary not loaded, not asserting serialization.');
        }
    }

    public function testSerializationShouldNotMutateCacheEntry()
    {
        $request = new Request(
            'GET',
            'test.local',
            [],
            'Sample body' // Always include a body in the request to be sure there is a stream in it
        );
        $response = new Response(
            200, [
            'Cache-Control' => 'max-age=60',
        ],
            'Test content'
        );
        $cacheEntry = new CacheEntry($request, $response, $this->makeDateTimeOffset(10));

        $originalCacheEntry = clone $cacheEntry;

        serialize($cacheEntry);

        $this->assertEquals($cacheEntry, $originalCacheEntry);
    }

    private function setResponseHeader($name, $value)
    {
        $this->responseHeaders[$name] = [$value];
    }

    private function makeDateTimeOffset($offset = 0)
    {
        return \DateTime::createFromFormat('U', time() + $offset);
    }
}
