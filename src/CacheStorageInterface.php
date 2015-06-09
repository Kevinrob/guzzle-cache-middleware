<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 07.06.2015
 * Time: 19:20
 */

namespace Kevinrob\GuzzleCache;


use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface CacheStorageInterface
{

    /**
     * Return a ResponseInterface with X-Cache-Fresh set:
     *  True if the cache entry in fresh
     *  False if the cache entry can be use when error occurred
     *
     * @param RequestInterface $request
     * @return ResponseInterface|null with X-Cache-Fresh set
     */
    function fetch(RequestInterface $request);

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return bool true if success
     */
    function cache(RequestInterface $request, ResponseInterface $response);

}