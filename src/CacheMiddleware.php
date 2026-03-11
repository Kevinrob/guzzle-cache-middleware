<?php

namespace Kevinrob\GuzzleCache;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class CacheMiddleware.
 */
class CacheMiddleware
{
    const HEADER_RE_VALIDATION = 'X-Kevinrob-GuzzleCache-ReValidation';
    const HEADER_INVALIDATION = 'X-Kevinrob-GuzzleCache-Invalidation';
    const HEADER_CACHE_INFO = 'X-Kevinrob-Cache';
    const HEADER_CACHE_HIT = 'HIT';
    const HEADER_CACHE_MISS = 'MISS';
    const HEADER_CACHE_STALE = 'STALE';

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
     * Value = true.
     *
     * @var array
     */
    protected $httpMethods = ['GET' => true];

    /**
     * List of safe methods
     *
     * https://datatracker.ietf.org/doc/html/rfc7231#section-4.2.1
     *
     * @var array
     */
    protected $safeMethods = ['GET' => true, 'HEAD' => true, 'OPTIONS' => true, 'TRACE' => true];

    /**
     * @param CacheStrategyInterface|null $cacheStrategy
     */
    public function __construct(?CacheStrategyInterface $cacheStrategy = null)
    {
        $this->cacheStorage = $cacheStrategy !== null ? $cacheStrategy : new PrivateCacheStrategy();

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
     * Will be called at the end of the script.
     */
    public function purgeReValidation()
    {
        \GuzzleHttp\Promise\Utils::inspectAll($this->waitingRevalidate);
    }

    /**
     * @param callable $handler
     *
     * @return callable
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use (&$handler) {
            if (!isset($this->httpMethods[strtoupper($request->getMethod())])) {
                // No caching for this method allowed

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request) {
                        if (!isset($this->safeMethods[$request->getMethod()])) {
                            // Invalidate cache after a call of non-safe method on the same URI
                            $response = $this->invalidateCache($request, $response);
                        }

                        return $response->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_MISS);
                    }
                );
            }

            if ($request->hasHeader(static::HEADER_RE_VALIDATION)) {
                // It's a re-validation request, so bypass the cache!
                return $handler($request->withoutHeader(static::HEADER_RE_VALIDATION), $options);
            }

            // Retrieve information from request (Cache-Control)
            $reqCacheControl = new KeyValueHttpHeader($request->getHeader('Cache-Control'));
            $onlyFromCache = $reqCacheControl->has('only-if-cached');
            $staleResponse = $reqCacheControl->has('max-stale')
                && $reqCacheControl->get('max-stale') === '';
            $maxStaleCache = $reqCacheControl->get('max-stale', null);
            $minFreshCache = $reqCacheControl->get('min-fresh', null);

            // If cache => return new FulfilledPromise(...) with response
            $cacheEntry = $this->cacheStorage->fetch($request);
            if ($cacheEntry instanceof CacheEntry) {
                $response = $cacheEntry->getResponse();
                $response->getBody()->rewind();

                if ($cacheEntry->isFresh()
                    && ($minFreshCache === null || $cacheEntry->getStaleAge() + (int)$minFreshCache <= 0)
                ) {
                    // Cache HIT!
                    $response = $response->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_HIT);

                    return new FulfilledPromise($this->applySink($response, $options));
                } elseif ($staleResponse
                    || ($maxStaleCache !== null && $cacheEntry->getStaleAge() <= $maxStaleCache)
                ) {
                    // Staled cache!
                    $response = $response->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_HIT);

                    return new FulfilledPromise($this->applySink($response, $options));
                } elseif ($cacheEntry->hasValidationInformation() && !$onlyFromCache) {
                    // Re-validation header
                    $request = static::getRequestWithReValidationHeader($request, $cacheEntry);

                    if ($cacheEntry->staleWhileValidate()) {
                        static::addReValidationRequest($request, $this->cacheStorage, $cacheEntry);

                        $response = $response->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_STALE);

                        return new FulfilledPromise($this->applySink($response, $options));
                    }
                }
            } else {
                $cacheEntry = null;
            }

            if ($cacheEntry === null && $onlyFromCache) {
                // Explicit asking of a cached response => 504
                return new FulfilledPromise(
                    new Response(504)
                );
            }

            /** @var Promise $promise */
            $promise = $handler($request, $options);

            return $promise->then(
                function (ResponseInterface $response) use ($request, $cacheEntry, $options) {
                    // Check if error and looking for a staled content
                    if ($response->getStatusCode() >= 500) {
                        $responseStale = static::getStaleResponse($cacheEntry);
                        if ($responseStale instanceof ResponseInterface) {
                            return $this->applySink($responseStale, $options);
                        }
                    }

                    $update = false;

                    if ($response->getStatusCode() == 304 && $cacheEntry instanceof CacheEntry) {
                        // Not modified => cache entry is re-validate
                        /** @var ResponseInterface $response */
                        $response = $response
                            ->withStatus($cacheEntry->getResponse()->getStatusCode())
                            ->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_HIT);
                        $response = $response->withBody($cacheEntry->getResponse()->getBody());

                        // Merge headers of the "304 Not Modified" and the cache entry
                        /**
                         * @var string $headerName
                         * @var string[] $headerValue
                         */
                        foreach ($cacheEntry->getOriginalResponse()->getHeaders() as $headerName => $headerValue) {
                            if (!$response->hasHeader($headerName) && $headerName !== static::HEADER_CACHE_INFO) {
                                $response = $response->withHeader($headerName, $headerValue);
                            }
                        }

                        $response = $this->applySink($response, $options);
                        $update = true;
                    } else {
                        $response = $response->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_MISS);
                    }

                    return static::addToCache($this->cacheStorage, $request, $response, $update);
                },
                function ($reason) use ($cacheEntry, $options) {
                    $response = static::getStaleResponse($cacheEntry);
                    if ($response instanceof ResponseInterface) {
                        return $this->applySink($response, $options);
                    }

                    return new RejectedPromise($reason);
                }
            );
        };
    }

    /**
     * @param CacheStrategyInterface $cache
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param bool $update cache
     * @return ResponseInterface
     */
    protected static function addToCache(
        CacheStrategyInterface $cache,
        RequestInterface $request,
        ResponseInterface $response,
        $update = false
    ) {
        $body = $response->getBody();

        // If the body is not seekable, we have to replace it by a seekable one
        if (!$body->isSeekable()) {
            $response = $response->withBody(
                Utils::streamFor($body->getContents())
            );
        }

        if ($update) {
            $cache->update($request, $response);
        } else {
            $cache->cache($request, $response);
        }

        // always rewind back to the start otherwise other middlewares may get empty "content"
        if ($body->isSeekable()) {
            $response->getBody()->rewind();
        }

        return $response;
    }

    protected function applySink(ResponseInterface $response, array $options): ResponseInterface
    {
        if (isset($options['sink'])) {
            return $response->withBody(
                $this->writeToSink($response->getBody(), $options['sink'])
            );
        }

        return $response;
    }

    protected function writeToSink(StreamInterface $body, mixed $sink): StreamInterface
    {
        if (is_string($sink)) {
            $sinkStream = Utils::streamFor(Utils::tryFopen($sink, 'w'));
        } elseif (is_resource($sink)) {
            $sinkStream = Utils::streamFor($sink);
        } elseif ($sink instanceof StreamInterface) {
            $sinkStream = $sink;
        } else {
            throw new \InvalidArgumentException('`sink` must be a resource, string, or StreamInterface');
        }

        try {
            $body->rewind();
            Utils::copyToStream($body, $sinkStream);
        } finally {
            $body->rewind();
            if ($sinkStream->isSeekable()) {
                $sinkStream->rewind();
            }
        }

        return $sinkStream;
    }

    /**
     * @param RequestInterface       $request
     * @param CacheStrategyInterface $cacheStorage
     * @param CacheEntry             $cacheEntry
     *
     * @return bool if added
     */
    protected function addReValidationRequest(
        RequestInterface $request,
        CacheStrategyInterface &$cacheStorage,
        CacheEntry $cacheEntry
    ) {
        // Add the promise for revalidate
        if ($this->client !== null) {
            /** @var RequestInterface $request */
            $request = $request->withHeader(static::HEADER_RE_VALIDATION, '1');
            $this->waitingRevalidate[] = $this->client
                ->sendAsync($request)
                ->then(function (ResponseInterface $response) use ($request, &$cacheStorage, $cacheEntry) {
                    $update = false;

                    if ($response->getStatusCode() == 304) {
                        // Not modified => cache entry is re-validate
                        /** @var ResponseInterface $response */
                        $response = $response->withStatus($cacheEntry->getResponse()->getStatusCode());
                        $response = $response->withBody($cacheEntry->getResponse()->getBody());

                        // Merge headers of the "304 Not Modified" and the cache entry
                        foreach ($cacheEntry->getResponse()->getHeaders() as $headerName => $headerValue) {
                            if (!$response->hasHeader($headerName)) {
                                $response = $response->withHeader($headerName, $headerValue);
                            }
                        }

                        $update = true;
                    }

                    static::addToCache($cacheStorage, $request, $response, $update);
                });

            return true;
        }

        return false;
    }

    /**
     * @param CacheEntry|null $cacheEntry
     *
     * @return null|ResponseInterface
     */
    protected static function getStaleResponse(?CacheEntry $cacheEntry = null)
    {
        // Return staled cache entry if we can
        if ($cacheEntry instanceof CacheEntry && $cacheEntry->serveStaleIfError()) {
            return $cacheEntry->getResponse()
                ->withHeader(static::HEADER_CACHE_INFO, static::HEADER_CACHE_STALE);
        }

        return null;
    }

    /**
     * @param RequestInterface $request
     * @param CacheEntry       $cacheEntry
     *
     * @return RequestInterface
     */
    protected static function getRequestWithReValidationHeader(RequestInterface $request, ?CacheEntry $cacheEntry)
    {
        if ($cacheEntry->getResponse()->hasHeader('Last-Modified')) {
            $request = $request->withHeader(
                'If-Modified-Since',
                $cacheEntry->getResponse()->getHeader('Last-Modified')
            );
        }
        if ($cacheEntry->getResponse()->hasHeader('Etag')) {
            $request = $request->withHeader(
                'If-None-Match',
                $cacheEntry->getResponse()->getHeader('Etag')
            );
        }

        return $request;
    }

    /**
     * @param CacheStrategyInterface|null $cacheStorage
     *
     * @return CacheMiddleware the Middleware for Guzzle HandlerStack
     *
     * @deprecated Use constructor => `new CacheMiddleware()`
     */
    public static function getMiddleware(?CacheStrategyInterface $cacheStorage = null)
    {
        return new self($cacheStorage);
    }

    /**
     * @param RequestInterface $request
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function invalidateCache(RequestInterface $request, ResponseInterface $response)
    {
        foreach (array_keys($this->httpMethods) as $method) {
            $this->cacheStorage->delete($request->withMethod($method));
        }

        return $response->withHeader(static::HEADER_INVALIDATION, true);
    }
}
