<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 21.06.2015
 * Time: 16:50
 */

namespace Kevinrob\GuzzleCache;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class DefaultPrivateCache extends AbstractPrivateCache
{
    /**
     * @var CacheEntry[]
     */
    protected $cache = [];


    /**
     * Return a CacheEntry or null if no cache.
     *
     * @param RequestInterface $request
     * @return CacheEntry|null
     */
    public function fetch(RequestInterface $request)
    {
        $key = $this->getCacheKey($request);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return null;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool true if success
     */
    public function cache(RequestInterface $request, ResponseInterface $response)
    {
        $entry = $this->getCacheObject($response);
        if ($entry instanceof CacheEntry) {
            $this->cache[$this->getCacheKey($request)] = $entry;
        }
    }

    /**
     * @param RequestInterface $request
     * @return string
     */
    protected function getCacheKey(RequestInterface $request)
    {
        return sha1(
            $request->getMethod() . $request->getUri()
        );
    }
}
