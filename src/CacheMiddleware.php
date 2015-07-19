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

class CacheMiddleware
{
    const CONFIG_STORAGE = 'storage';

    /**
     * @var array of Promise
     */
    protected $waitingRevalidate = [];

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var CacheStorageInterface
     */
    protected $cacheStorage;


    /**
     * @param CacheStorageInterface|null $cacheStorage
     */
    public function __construct(CacheStorageInterface $cacheStorage = null)
    {
        $this->cacheStorage = $cacheStorage !== null ? $cacheStorage : new PrivateCache();

        register_shutdown_function([$this, 'purgeReValidation']);
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    public function purgeReValidation()
    {
        \GuzzleHttp\Promise\inspect_all($this->waitingRevalidate);
    }

    /**
     * @param $handler
     * @return \Closure
     */
    public function __invoke($handler)
    {
        return function (RequestInterface $request, array $options) use (&$handler) {
            $reqMethod = $request->getMethod();
            if ($reqMethod !== 'GET' && $reqMethod !== 'HEAD') {
                // No caching for others methods
                return $handler($request, $options);
            }

            if ($request->hasHeader("X-ReValidation")) {
                return $handler($request->withoutHeader("X-ReValidation"), $options);
            }

            // If cache => return new FulfilledPromise(...) with response
            $cacheEntry = $this->cacheStorage->fetch($request);
            if ($cacheEntry instanceof CacheEntry) {
                if ($cacheEntry->isFresh()) {
                    // Cache HIT!
                    return new FulfilledPromise($cacheEntry->getResponse()->withHeader("X-Cache", "HIT"));
                } elseif ($cacheEntry->hasValidationInformation()) {
                    // Re-validation header
                    $request = static::getRequestWithReValidationHeader($request, $cacheEntry);

                    if ($cacheEntry->staleWhileValidate()) {
                        static::addReValidationRequest($request, $this->cacheStorage, $cacheEntry);

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
                function (ResponseInterface $response) use ($request, $cacheEntry) {
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
                            ->withHeader("X-Cache", "HIT with validation");
                        $response = $response->withBody($cacheEntry->getResponse()->getBody());
                    }

                    // Add to the cache
                    $this->cacheStorage->cache($request, $response);

                    return $response;
                },
                function (\Exception $ex) use ($cacheEntry) {
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
     * @param CacheStorageInterface $cacheStorage
     * @param CacheEntry $cacheEntry
     * @return bool if added
     */
    protected function addReValidationRequest(
        RequestInterface $request,
        CacheStorageInterface & $cacheStorage,
        CacheEntry $cacheEntry
    ) {
        // Add the promise for revalidate
        if ($this->client !== null) {
            /** @var RequestInterface $request */
            $request = $request->withHeader("X-ReValidation", "1");
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
     * @param CacheEntry $cacheEntry
     * @return null|ResponseInterface
     */
    protected static function getStaleResponse(CacheEntry $cacheEntry = null)
    {
        // Return staled cache entry if we can
        if ($cacheEntry instanceof CacheEntry && $cacheEntry->serveStaleIfError()) {
            return $cacheEntry->getResponse()
                ->withHeader("X-Cache", "HIT stale");
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
     * @param CacheStorageInterface $cacheStorage
     * @return \Closure the Middleware for Guzzle HandlerStack
     *
     * @deprecated Use constructor => `new CacheMiddleware()`
     */
    public static function getMiddleware(CacheStorageInterface $cacheStorage = null)
    {
        return new self($cacheStorage);
    }

}
