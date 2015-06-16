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
     * @return array|null
     */
    protected function getCacheObject(ResponseInterface $response)
    {
        if ($response->hasHeader("Cache-Control")) {
            $cacheControlDirectives = $response->getHeader("Cache-Control");

            if (in_array("no-store", $cacheControlDirectives)) {
                return null;
            }

            // TODO return info for caching (stale, revalidate, ...)
        }

        if ($response->hasHeader("Expires")) {
            return [
                'response' => $response,
            ];
        }

        return null;
    }

}