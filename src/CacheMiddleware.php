<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 07.06.2015
 * Time: 15:44
 */

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

class CacheMiddleware
{
    const CONFIG_STORAGE = 'storage';

    /**
     * @param array $config
     * @return \Closure the Middleware for Guzzle HandlerStack
     */
    public static function getMiddleware(array $config = [])
    {
        if (isset($config[self::CONFIG_STORAGE])) {
            if (! $config[self::CONFIG_STORAGE] instanceof CacheStorageInterface) {
                throw new \InvalidArgumentException(
                    'Storage for cache must implement CacheStorageInterface. ' .
                    '"' . get_class($config[self::CONFIG_STORAGE]) . '" given.'
                );
            }

            /** @var CacheStorageInterface $cacheStorage */
            $cacheStorage = $config[self::CONFIG_STORAGE];
        } else {
            $cacheStorage = new DefaultPrivateCache();
        }

        return function (callable $handler) use ($cacheStorage) {
            return function ($request, array $options) use ($handler, $cacheStorage) {
                // If cache => return new FulfilledPromise(...) with response
                $cacheEntry = $cacheStorage->fetch($request);
                if ($cacheEntry != null && $cacheEntry->isFresh()) {
                    // Cache HIT!
                    return new FulfilledPromise($cacheEntry->getResponse()->withHeader("X-Cache", "HIT"));
                }

                /** @var Promise $promise */
                $promise = $handler($request, $options);
                return $promise->then(
                    function (ResponseInterface $response) use ($request, $cacheStorage) {
                        if ($response->getStatusCode() >= 500) {
                            // Find a stale response to serve
                            $cacheEntry = $cacheStorage->fetch($request);
                            if ($cacheEntry != null && $cacheEntry->serveStaleIfError()) {
                                return new FulfilledPromise($cacheEntry->getResponse());
                            }
                        }

                        // Add to the cache
                        $cacheStorage->cache($request, $response);

                        return $response;
                    }
                );
            };
        };
    }

}