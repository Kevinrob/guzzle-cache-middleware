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
        if ($response->hasHeader("Cache-Control")) {
            $values = new KeyValueHttpHeader($response->getHeader("Cache-Control"));

            if ($values->has('no-store')) {
                // No store allowed (maybe some sensitives data...)
                return null;
            }

            if ($values->has('no-cache')) {
                // Stale response see RFC7234 section 5.2.1.4
                $entry = new CacheEntry($response, new \DateTime('-1 seconds'));
                return $entry->hasValidationInformation() ? $entry : null;
            }

            if ($values->has('max-age')) {
                return new CacheEntry(
                    $response,
                    new \DateTime('+' . $values->get('max-age') . 'seconds')
                );
            }
        }

        if ($response->hasHeader("Expires")) {
            $expireAt = \DateTime::createFromFormat('D, d M Y H:i:s T', $response->getHeaderLine("Expires"));
            if ($expireAt !== FALSE) {
                return new CacheEntry($response, $expireAt);
            }
        }

        return new CacheEntry($response, new \DateTime('1 days ago'));
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
            if(isset($cacheObject))
            {
                $lifeTime = $this->getCacheObject($response)->getStaleAt()->getTimestamp() - time();
                if($lifeTime > 0) {
                    return $this->storage->save($this->getCacheKey($request), serialize($this->getCacheObject($response)), $lifeTime);
                }
            }
        } catch (\Exception $ignored) {
            return false;
        }
        
        return false;
    }
}
