<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 07.06.2015
 * Time: 15:44
 */

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

class CacheMiddleware
{

    public static function getMiddleware()
    {
        return function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                // TODO Add logic here for HTTP Caching
                // If cache => return new FulfilledPromise with response

                /** @var Promise $promise */
                $promise = $handler($request, $options);
                return $promise->then(
                    function (ResponseInterface $response) use (&$cache) {
                        // TODO Add logic for adding response to cache

                        return $response;
                    }
                );
            };
        };
    }

}