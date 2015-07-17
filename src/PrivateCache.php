<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 16.06.2015
 * Time: 19:10
 */

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
            $cacheControlDirectives = $response->getHeader("Cache-Control");

            if (in_array("no-store", $cacheControlDirectives)) {
                // No store allowed (maybe some sensitives data...)
                return null;
            }

            if (in_array("no-cache", $cacheControlDirectives)) {
                // Stale response see RFC7234 section 5.2.1.4
                $entry = new CacheEntry($response, new \DateTime('-1 seconds'));
                return $entry->hasValidationInformation() ? $entry : null;
            }

            $matches = [];
            if (preg_match('/^max-age=([0-9]*)$/', $response->getHeaderLine("Cache-Control"), $matches)) {
                // Handle max-age header
                return new CacheEntry($response, new \DateTime('+' . $matches[1] . 'seconds'));
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
            return $this->storage->fetch($this->getCacheKey($request));
        } catch (\Exception $ignored) {
            return null;
        }
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool true if success
     */
    public function cache(RequestInterface $request, ResponseInterface $response)
    {
        try {
            return $this->storage->save($this->getCacheKey($request), $this->getCacheObject($response));
        } catch (\Exception $ignored) {
            return false;
        }
    }
}
