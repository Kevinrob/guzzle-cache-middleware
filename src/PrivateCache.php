<?php

namespace Kevinrob\GuzzleCache;


use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\Cache;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class PrivateCache implements CacheStorageInterface
{

    /**
     * @var Cache
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

    public function __construct(Cache $cache = null)
    {
        $this->storage = $cache !== null ? $cache : new ArrayCache();
    }

    /**
     * @param ResponseInterface $response
     * @return CacheEntry|null entry to save, null if can't cache it
     */
    protected function getCacheObject(ResponseInterface $response)
    {
        if (!isset($this->statusAccepted[$response->getStatusCode()])) {
            // Don't cache it
            return null;
        }

        if ($response->hasHeader("Cache-Control")) {
            $values = new KeyValueHttpHeader($response->getHeader("Cache-Control"));

            if (!$values->isEmpty()) {
                return $this->getCacheObjectForCacheControl($response, $values);
            }
        }

        if ($response->hasHeader("Expires")
            && $expireAt = \DateTime::createFromFormat(\DateTime::RFC1123, $response->getHeaderLine("Expires"))) {
            return new CacheEntry($response, $expireAt);
        }

        return new CacheEntry($response, new \DateTime('-1 seconds'));
    }

    /**
     * @param ResponseInterface $response
     * @param KeyValueHttpHeader $cacheControl
     * @return CacheEntry|null
     */
    protected function getCacheObjectForCacheControl(ResponseInterface $response, KeyValueHttpHeader $cacheControl)
    {
        if ($cacheControl->has('no-store')) {
            // No store allowed (maybe some sensitives data...)
            return null;
        }

        if ($cacheControl->has('no-cache')) {
            // Stale response see RFC7234 section 5.2.1.4
            $entry = new CacheEntry($response, new \DateTime('-1 seconds'));
            return $entry->hasValidationInformation() ? $entry : null;
        }

        if ($cacheControl->has('max-age')) {
            return new CacheEntry(
                $response,
                new \DateTime('+' . (int)$cacheControl->get('max-age') . 'seconds')
            );
        }

        return new CacheEntry($response, new \DateTime());
    }

    /**
     * @param RequestInterface $request
     * @return string
     */
    protected function getCacheKey(RequestInterface $request)
    {
        return sha1(
            $request->getMethod() . $request->getUri()
        );
    }

    /**
     * Return a CacheEntry or null if no cache.
     *
     * @param RequestInterface $request
     * @return CacheEntry|null
     */
    public function fetch(RequestInterface $request)
    {
        try {
            $cache = unserialize($this->storage->fetch($this->getCacheKey($request)));
            if ($cache instanceof CacheEntry) {
                return $cache;
            }
        } catch (\Exception $ignored) {
            return null;
        }

        return null;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool true if success
     */
    public function cache(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $cacheObject = $this->getCacheObject($response);
            if ($cacheObject !== null)
            {
                $lifeTime = $cacheObject->getTTL();
                if ($lifeTime >= 0) {
                    return $this->storage->save(
                        $this->getCacheKey($request),
                        serialize($cacheObject),
                        $lifeTime
                    );
                }
            }
        } catch (\Exception $ignored) {
            return false;
        }
        
        return false;
    }
}
