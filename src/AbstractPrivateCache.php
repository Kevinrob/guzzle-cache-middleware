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

            foreach ($cacheControlDirectives as $directive) {
                $matches = [];

                if (preg_match('/^max-age=([0-9]*)$/', $directive, $matches)) {
                    // Handle max-age header
                    return new CacheEntry($response, new \DateTime('+' . $matches[1] . 'seconds'));
                }
            }
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
