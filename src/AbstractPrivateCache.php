<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 16.06.2015
 * Time: 19:10
 */

namespace Kevinrob\GuzzleCache;


use Psr\Http\Message\ResponseInterface;

abstract class AbstractPrivateCache implements CacheStorageInterface
{

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

            // TODO return info for caching (stale, revalidate, ...)
        }

        if ($response->hasHeader("Expires")) {
            $expireAt = \DateTime::createFromFormat('D, d M Y H:i:s T', $response->getHeaderLine("Expires"));
            if ($expireAt !== FALSE) {
                return new CacheEntry($response, $expireAt);
            }
        }

        return null;
    }

}