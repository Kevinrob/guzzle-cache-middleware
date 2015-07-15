<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 07.06.2015
 * Time: 15:44
 */

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Promise\unwrap;

class CacheMiddleware
{
    const CONFIG_STORAGE = 'storage';

    /**
     * @var array of Promise
     */
    protected static $waitingRevalidate = [];

    /**
     * @var Client
     */
    protected static $client;

    public static function setClient(Client $client)
    {
        static::$client = $client;
    }

    public static function purgeReValidation()
    {
        unwrap(static::$waitingRevalidate);
    }

    /**
     * @param CacheStorageInterface $cacheStorage
     * @return \Closure the Middleware for Guzzle HandlerStack
     */
    public static function getMiddleware(CacheStorageInterface $cacheStorage = null)
    {
        if ($cacheStorage === null) {
            $cacheStorage = new PrivateCache();
        }

        return function(callable $handler) use (&$cacheStorage) {
            return function(RequestInterface $request, array $options) use ($handler, &$cacheStorage) {
                $reqMethod = $request->getMethod();
                if ($reqMethod !== 'GET' && $reqMethod !== 'HEAD') {
                    // No caching for others methods
                    return $handler($request, $options);
                }

                if ($request->hasHeader("X-ReValidation")) {
                    return $handler($request->withoutHeader("X-ReValidation"), $options);
                }

                // If cache => return new FulfilledPromise(...) with response
                $cacheEntry = $cacheStorage->fetch($request);
                if ($cacheEntry instanceof CacheEntry) {
                    if ($cacheEntry->isFresh()) {
                        // Cache HIT!
                        return new FulfilledPromise($cacheEntry->getResponse()->withHeader("X-Cache", "HIT"));
                    } elseif ($cacheEntry->hasValidationInformation()) {
                        // Re-validation header
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

                        if ($cacheEntry->staleWhileValidate()) {
                            // Add the promise for revalidate
                            if (static::$client !== null) {
                                static::$waitingRevalidate[] = static::$client
                                    ->sendAsync(
                                        $request->withHeader("X-ReValidation", "1")
                                    )
                                    ->then(function (ResponseInterface $response) use ($request, &$cacheStorage, $cacheEntry) {
                                        if ($response->getStatusCode() == 304) {
                                            // Not modified => cache entry is re-validate
                                            /** @var ResponseInterface $response */
                                            $response = $response->withStatus($cacheEntry->getResponse()->getStatusCode());
                                            $response = $response->withBody($cacheEntry->getResponse()->getBody());
                                        }

                                        $cacheStorage->cache($request, $response);
                                    });
                            }

                            return new FulfilledPromise(
                                $cacheEntry->getResponse()
                                    ->withHeader("X-Cache", "Stale while revalidate")
                            );
                        }
                    }
                }

                /** @var Promise $promise */
                $promise = $handler($request, $options);
                return $promise->then(
                    function(ResponseInterface $response) use ($request, &$cacheStorage, $cacheEntry) {
                        if ($response->getStatusCode() >= 500) {
                            $responseStale = CacheMiddleware::getStaleResponse($cacheEntry);
                            if ($responseStale instanceof ResponseInterface) {
                                return $responseStale;
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
                    },
                    function(\Exception $ex) use ($cacheEntry) {
                        if ($ex instanceof TransferException) {
                            $response = CacheMiddleware::getStaleResponse($cacheEntry);
                            if ($response instanceof ResponseInterface) {
                                return $response;
                            }
                        }

                        throw $ex;
                    }
                );
            };
        };
    }

    /**
     * @param CacheEntry $cacheEntry
     * @return null|ResponseInterface
     */
    public static function getStaleResponse(CacheEntry $cacheEntry = null)
    {
        // Return staled cache entry if we can
        if ($cacheEntry instanceof CacheEntry && $cacheEntry->serveStaleIfError()) {
            return $cacheEntry->getResponse()
                ->withHeader("X-Cache", "HIT stale");
        }

        return null;
    }

}
