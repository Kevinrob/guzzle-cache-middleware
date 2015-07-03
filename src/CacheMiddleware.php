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
use Psr\Http\Message\RequestInterface;
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
            if (!$config[self::CONFIG_STORAGE] instanceof CacheStorageInterface) {
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

        return function(callable $handler) use ($cacheStorage) {
            return function(RequestInterface $request, array $options) use ($handler, $cacheStorage) {
                $reqMethod = $request->getMethod();
                if ($reqMethod !== 'GET' && $reqMethod !== 'HEAD') {
                    // No caching for others methods
                    return $handler($request, $options);
                }

                // If cache => return new FulfilledPromise(...) with response
                $cacheEntry = $cacheStorage->fetch($request);
                if ($cacheEntry instanceof CacheEntry) {
                    if ($cacheEntry->isFresh()) {
                        // Cache HIT!
                        return new FulfilledPromise($cacheEntry->getResponse()->withHeader("X-Cache", "HIT"));
                    } else {
                        // Re-validation header?
                        if ($cacheEntry->getResponse()->hasHeader("Last-Modified")) {
                            $request = $request->withHeader(
                                "If-Modified-Since",
                                $cacheEntry->getResponse()->getHeader("Last-Modified")
                            );
                        }
                        if ($cacheEntry->getResponse()->hasHeader("Etag")) {
                            $request = $request->withHeader(
                                "If-None-Match",
                                $cacheEntry->getResponse()->getHeader("Etag")
                            );
                        }
                    }
                }

                /** @var Promise $promise */
                $promise = $handler($request, $options);
                return $promise->then(
                    function(ResponseInterface $response) use ($request, $cacheStorage, $cacheEntry) {
                        if ($response->getStatusCode() >= 500) {
                            // Return staled cache entry if we can
                            if ($cacheEntry instanceof CacheEntry && $cacheEntry->serveStaleIfError()) {
                                return $cacheEntry->getResponse()
                                    ->withHeader("X-Cache", "HIT stale");
                            }
                        }

                        if ($response->getStatusCode() == 304 && $cacheEntry instanceof CacheEntry) {
                            // Not modified => cache entry is re-validate
                            /** @var ResponseInterface $response */
                            $response = $response
                                ->withStatus($cacheEntry->getResponse()->getStatusCode())
                                ->withHeader("X-Cache", "HIT with validation")
                            ;
                            $response = $response->withBody($cacheEntry->getResponse()->getBody());
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
