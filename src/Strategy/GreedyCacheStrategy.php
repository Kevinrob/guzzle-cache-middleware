<?php

namespace Kevinrob\GuzzleCache\Strategy;

use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This strategy represents a "greedy" HTTP client.
 *
 * It can be used to cache responses in spite of any cache related response headers,
 * but it SHOULDN'T be used unless absolutely necessary, e.g. when accessing
 * badly designed APIs without Cache control.
 *
 * Obviously, this follows no RFC :(.
 */
class GreedyCacheStrategy extends PrivateCacheStrategy
{
    /**
     * @var int
     */
    protected $ttl;

    public function __construct(CacheStorageInterface $cache = null, $ttl)
    {
        $this->ttl = $ttl;

        parent::__construct($cache);
    }

    protected function getCacheKey(RequestInterface $request)
    {
        return hash(
            'sha256',
            'greedy'.$request->getMethod().$request->getUri()
        );
    }

    public function cache(RequestInterface $request, ResponseInterface $response)
    {
        $warningMessage = sprintf('%d - "%s" "%s"',
            299,
            'Cached although the response headers indicate not to do it!',
            (new \DateTime())->format(\DateTime::RFC1123)
        );

        $response = $response->withAddedHeader('Warning', $warningMessage);

        if ($cacheObject = $this->getCacheObject($request, $response)) {
            return $this->storage->save(
                $this->getCacheKey($request),
                $cacheObject
            );
        }

        return false;
    }

    protected function getCacheObject(RequestInterface $request, ResponseInterface $response)
    {
        if (!array_key_exists($response->getStatusCode(), $this->statusAccepted)) {
            // Don't cache it
            return null;
        }

        return new CacheEntry($request, $response, new \DateTime(sprintf('+%d seconds', $this->ttl)));
    }

    public function fetch(RequestInterface $request)
    {
        return $this->storage->fetch($this->getCacheKey($request));
    }
}
