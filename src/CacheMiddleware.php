<?php

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CacheMiddleware
 * @package Kevinrob\GuzzleCache
 */
class CacheMiddleware
{
    const HEADER_RE_VALIDATION  = 'X-Kevinrob-GuzzleCache-ReValidation';
    const HEADER_CACHE_INFO     = 'X-Kevinrob-Cache';
    const HEADER_CACHE_HIT      = 'HIT';
    const HEADER_CACHE_MISS     = 'MISS';
    const HEADER_CACHE_STALE    = 'STALE';

    /**
     * @var array of Promise
     */
    protected $waitingRevalidate = [];

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var CacheStrategyInterface
     */
    protected $cacheStorage;

    /**
     * List of allowed HTTP methods to cache
     * Key = method name (upscaling)
     * Value = true
     *
     * @var array
     */
    protected $httpMethods = ['GET' => true];


    /**
     * @param CacheStrategyInterface|null $cacheStorage
     */
    public function __construct(CacheStrategyInterface $cacheStorage = null)
    {
        $this->cacheStorage = $cacheStorage !== null ? $cacheStorage : new PrivateCacheStrategy();

        register_shutdown_function([$this, 'purgeReValidation']);
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param CacheStrategyInterface $cacheStorage
     */
    public function setCacheStorage(CacheStrategyInterface $cacheStorage)
    {
        $this->cacheStorage = $cacheStorage;
    }

    /**
     * @return CacheStrategyInterface
     */
    public function getCacheStorage()
    {
        return $this->cacheStorage;
    }

    /**
     * @param array $methods
     */
    public function setHttpMethods(array $methods)
    {
        $this->httpMethods = $methods;
    }

    public function getHttpMethods()
    {
        return $this->httpMethods;
    }

    /**
     * Will be called at the end of the script
     */
    public function purgeReValidation()
    {
        \GuzzleHttp\Promise\inspect_all($this->waitingRevalidate);
    }

    /**
     * @param \Closure $handler
     * @return \Closure
     */
    public function __invoke(\Closure $handler)
    {
        return function(RequestInterface $request, array $options) use (&$handler) {
            if (!isset($this->httpMethods[strtoupper($request->getMethod())])) {
                // No caching for this method allowed
                return $handler($request, $options)->then(
                    function(ResponseInterface $response) {
                        return $response->withHeader(self::HEADER_CACHE_INFO, self::HEADER_CACHE_MISS);
                    }
                );
            }

            if ($request->hasHeader(self::HEADER_RE_VALIDATION)) {
                // It's a re-validation request, so bypass the cache!
                return $handler($request->withoutHeader(self::HEADER_RE_VALIDATION), $options);
            }

            // If cache => return new FulfilledPromise(...) with response
            $cacheEntry = $this->cacheStorage->fetch($request);
            if ($cacheEntry instanceof CacheEntry) {
                if ($cacheEntry->isFresh()) {
                    // Cache HIT!
                    return new FulfilledPromise($cacheEntry->getResponse()->withHeader(self::HEADER_CACHE_INFO, self::HEADER_CACHE_HIT));
                } elseif ($cacheEntry->hasValidationInformation()) {
                    // Re-validation header
                    $request = static::getRequestWithReValidationHeader($request, $cacheEntry);

                    if ($cacheEntry->staleWhileValidate()) {
                        static::addReValidationRequest($request, $this->cacheStorage, $cacheEntry);

                        return new FulfilledPromise(
                            $cacheEntry->getResponse()
                                ->withHeader(self::HEADER_CACHE_INFO, self::HEADER_CACHE_STALE)
                        );
                    }
                }
            } else {
                $cacheEntry = null;
            }

            /** @var Promise $promise */
            $promise = $handler($request, $options);
            return $promise->then(
                function(ResponseInterface $response) use ($request, $cacheEntry) {
                    // Check if error and looking for a staled content
                    if ($response->getStatusCode() >= 500) {
                        $responseStale = static::getStaleResponse($cacheEntry);
                        if ($responseStale instanceof ResponseInterface) {
                            return $responseStale;
                        }
                    }

                    if ($response->getStatusCode() == 304 && $cacheEntry instanceof CacheEntry) {
                        // Not modified => cache entry is re-validate
                        /** @var ResponseInterface $response */
                        $response = $response
                            ->withStatus($cacheEntry->getResponse()->getStatusCode())
                            ->withHeader(self::HEADER_CACHE_INFO, self::HEADER_CACHE_HIT);
                        $response = $response->withBody($cacheEntry->getResponse()->getBody());
                    } else {
                        $response = $response->withHeader(self::HEADER_CACHE_INFO, self::HEADER_CACHE_MISS);
                    }

                    // Add to the cache
                    $this->cacheStorage->cache($request, $response);

                    return $response;
                },
                function(\Exception $ex) use ($cacheEntry) {
                    if ($ex instanceof TransferException) {
                        $response = static::getStaleResponse($cacheEntry);
                        if ($response instanceof ResponseInterface) {
                            return $response;
                        }
                    }

                    throw $ex;
                }
            );
        };
    }

    /**
     * @param RequestInterface $request
     * @param CacheStrategyInterface $cacheStorage
     * @param CacheEntry $cacheEntry
     * @return bool if added
     */
    protected function addReValidationRequest(
        RequestInterface $request,
        CacheStrategyInterface & $cacheStorage,
        CacheEntry $cacheEntry
    ) {
        // Add the promise for revalidate
        if ($this->client !== null) {
            /** @var RequestInterface $request */
            $request = $request->withHeader(self::HEADER_RE_VALIDATION, "1");
            $this->waitingRevalidate[] = $this->client
                ->sendAsync($request)
                ->then(function(ResponseInterface $response) use ($request, &$cacheStorage, $cacheEntry) {
                    if ($response->getStatusCode() == 304) {
                        // Not modified => cache entry is re-validate
                        /** @var ResponseInterface $response */
                        $response = $response->withStatus($cacheEntry->getResponse()->getStatusCode());
                        $response = $response->withBody($cacheEntry->getResponse()->getBody());
                    }

                    $cacheStorage->cache($request, $response);
                });

            return true;
        }

        return false;
    }

    /**
     * @param CacheEntry|null $cacheEntry
     * @return null|ResponseInterface
     */
    protected static function getStaleResponse(CacheEntry $cacheEntry = null)
    {
        // Return staled cache entry if we can
        if ($cacheEntry instanceof CacheEntry && $cacheEntry->serveStaleIfError()) {
            return $cacheEntry->getResponse()
                ->withHeader(self::HEADER_CACHE_INFO, self::HEADER_CACHE_STALE);
        }

        return null;
    }

    /**
     * @param RequestInterface $request
     * @param CacheEntry $cacheEntry
     * @return RequestInterface
     */
    protected static function getRequestWithReValidationHeader(RequestInterface $request, CacheEntry $cacheEntry)
    {
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

        return $request;
    }

    /**
     * @param CacheStrategyInterface|null $cacheStorage
     * @return CacheMiddleware the Middleware for Guzzle HandlerStack
     *
     * @deprecated Use constructor => `new CacheMiddleware()`
     */
    public static function getMiddleware(CacheStrategyInterface $cacheStorage = null)
    {
        return new self($cacheStorage);
    }

}
