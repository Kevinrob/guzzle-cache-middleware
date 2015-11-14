<?php

namespace Kevinrob\GuzzleCache\Strategy;

use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This strategy represent a "private" HTTP client.
 * Pay attention to share storage between application with caution!
 *
 * For example, a response with cache-control header "private, max-age=60"
 * will be cached by this strategy.
 *
 * The rules applied are from RFC 7234.
 *
 * @see https://tools.ietf.org/html/rfc7234
 */
class PrivateCacheStrategy implements CacheStrategyInterface
{
    /**
     * @var CacheStorageInterface
     */
    protected $storage;

    /**
     * @var int[]
     */
    protected $statusAccepted = [
        200 => 200,
        203 => 203,
        204 => 204,
        300 => 300,
        301 => 301,
        404 => 404,
        405 => 405,
        410 => 410,
        414 => 414,
        418 => 418,
        501 => 501,
    ];

    /**
     * @var string[]
     */
    protected $ageKey = [
        'max-age',
    ];

    public function __construct(CacheStorageInterface $cache = null)
    {
        $this->storage = $cache !== null ? $cache : new VolatileRuntimeStorage();
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return CacheEntry|null entry to save, null if can't cache it
     */
    protected function getCacheObject(RequestInterface $request, ResponseInterface $response)
    {
        if (!isset($this->statusAccepted[$response->getStatusCode()])) {
            // Don't cache it
            return;
        }

        $cacheControl = new KeyValueHttpHeader($response->getHeader('Cache-Control'));
        $varyHeader = new KeyValueHttpHeader($response->getHeader('Vary'));

        if ($varyHeader->has('*')) {
            // This will never match with a request
            return;
        }

        if ($cacheControl->has('no-store')) {
            // No store allowed (maybe some sensitives data...)
            return;
        }

        if ($cacheControl->has('no-cache')) {
            // Stale response see RFC7234 section 5.2.1.4
            $entry = new CacheEntry($request, $response, new \DateTime('-1 seconds'));

            return $entry->hasValidationInformation() ? $entry : null;
        }

        foreach ($this->ageKey as $key) {
            if ($cacheControl->has($key)) {
                return new CacheEntry(
                    $request,
                    $response,
                    new \DateTime('+'.(int) $cacheControl->get($key).'seconds')
                );
            }
        }

        if ($response->hasHeader('Expires')) {
            $expireAt = \DateTime::createFromFormat(\DateTime::RFC1123, $response->getHeaderLine('Expires'));
            if ($expireAt !== false) {
                return new CacheEntry(
                    $request,
                    $response,
                    $expireAt
                );
            }
        }

        return new CacheEntry($request, $response, new \DateTime('-1 seconds'));
    }

    /**
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function getCacheKey(RequestInterface $request)
    {
        $paramsKey = $this->getParamsKey($request);

        return sha1(
            $request->getMethod().$request->getUri().$paramsKey
        );
    }

    /**
     * Return a CacheEntry or null if no cache.
     *
     * @param RequestInterface $request
     *
     * @return CacheEntry|null
     */
    public function fetch(RequestInterface $request)
    {
        /** @var int|null $maxAge */
        $maxAge = null;

        if ($request->hasHeader('Cache-Control')) {
            $reqCacheControl = new KeyValueHttpHeader($request->getHeader('Cache-Control'));
            if ($reqCacheControl->has('no-cache')) {
                // Can't return cache
                return null;
            }

            $maxAge = $reqCacheControl->get('max-age', null);
        } elseif ($request->hasHeader('Pragma')) {
            $pragma = new KeyValueHttpHeader($request->getHeader('Pragma'));
            if ($pragma->has('no-cache')) {
                // Can't return cache
                return null;
            }
        }

        $cache = $this->storage->fetch($this->getCacheKey($request));
        if ($cache !== null) {
            if ($maxAge !== null) {
                if ($cache->getAge() > $maxAge) {
                    // Cache entry is too old for the request requirements!
                    return null;
                }
            }

            if (!$cache->isVaryEquals($request)) {
                return null;
            }
        }

        return $cache;
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return bool true if success
     */
    public function cache(RequestInterface $request, ResponseInterface $response)
    {
        $reqCacheControl = new KeyValueHttpHeader($request->getHeader('Cache-Control'));
        if ($reqCacheControl->has('no-store')) {
            // No caching allowed
            return false;
        }

        $cacheObject = $this->getCacheObject($request, $response);
        if ($cacheObject !== null) {
            return $this->storage->save(
                $this->getCacheKey($request),
                $cacheObject
            );
        }

        return false;
    }
    
    /**
     * @param RequestInterface $request;

     * @return string
     */

    private function getParamsKey(RequestInterface $request)
    {
        $body = (String) $request->getBody();

        return sha1($body);
    }
}
