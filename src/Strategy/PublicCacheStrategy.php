<?php

namespace Kevinrob\GuzzleCache\Strategy;


use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This strategy represent a "public" or "shared" HTTP client.
 * You can share hte storage between application.
 *
 * For example, a response with cache-control header "private, max-age=60"
 * will be NOT cached by this strategy.
 *
 * The rules applied are from RFC 7234.
 *
 * @see https://tools.ietf.org/html/rfc7234
 *
 * @package Kevinrob\GuzzleCache\Strategy
 */
class PublicCacheStrategy extends PrivateCacheStrategy
{

    public function __construct(CacheStorageInterface $cache = null)
    {
        parent::__construct($cache);

        array_unshift($this->ageKey, 's-maxage');
    }

    /**
     * @param ResponseInterface $response
     * @param KeyValueHttpHeader $cacheControl
     * @return CacheEntry|null
     */
    protected function getCacheObjectForCacheControl(ResponseInterface $response, KeyValueHttpHeader $cacheControl)
    {
        if ($cacheControl->has('private')) {
            return null;
        }

        return parent::getCacheObjectForCacheControl($response, $cacheControl);
    }

}
