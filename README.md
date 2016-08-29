# guzzle-cache-middleware

[![Latest Stable Version](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/v/stable)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![Total Downloads](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/downloads)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![License](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/license)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware)  
[![Build Status](https://travis-ci.org/Kevinrob/guzzle-cache-middleware.svg?branch=master)](https://travis-ci.org/Kevinrob/guzzle-cache-middleware) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Kevinrob/guzzle-cache-middleware/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Kevinrob/guzzle-cache-middleware/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/077ec9d6-9362-43be-83c9-cf1db2c9c802/mini.png)](https://insight.sensiolabs.com/projects/077ec9d6-9362-43be-83c9-cf1db2c9c802)  
[![Dependency Status](https://www.versioneye.com/php/kevinrob:guzzle-cache-middleware/badge.png)](https://www.versioneye.com/php/kevinrob:guzzle-cache-middleware)  


A HTTP Cache for [Guzzle](https://github.com/guzzle/guzzle) 6. It's a simple Middleware to be added in the HandlerStack.

## Goals
- RFC 7234 compliance
- Performance and transparency
- Assured compatibility with PSR-7

## Storage interfaces build-in
- [Doctrine cache](https://github.com/doctrine/cache)
- [Laravel cache](https://laravel.com/docs/5.2/cache)
- [Flysystem](https://github.com/thephpleague/flysystem)
- [PSR6](https://github.com/php-fig/cache)

## Installation

`composer require kevinrob/guzzle-cache-middleware`

or add it the your `composer.json` and make a `composer update kevinrob/guzzle-cache-middleware`.

# Why?
Performance. It's very common to do some HTTP calls to an API for rendering a page and it takes times to do it.

# How?
With a simple Middleware added at the top of the `HandlerStack` of Guzzle6.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;

// Create default HandlerStack
$stack = HandlerStack::create();

// Add this middleware to the top with `push`
$stack->push(new CacheMiddleware(), 'cache');

// Initialize the client with the handler option
$client = new Client(['handler' => $stack]);
```

# Examples

## Doctrine/Cache
You can use a cache from `Doctrine/Cache`:
```php
[...]
use Doctrine\Common\Cache\FilesystemCache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;

[...]
$stack->push(
  new CacheMiddleware(
    new PrivateCacheStrategy(
      new DoctrineCacheStorage(
        new FilesystemCache('/tmp/')
      )
    )
  ), 
  'cache'
);
```

You can use `ChainCache` for using multiple `CacheProvider`. With that provider, you have to sort the different cache from the faster to the slower. Like that, you can have a very fast cache.
```php
[...]
use Doctrine\Common\Cache\ChainCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;

[...]
$stack->push(new CacheMiddleware(
  new PrivateCacheStrategy(
    new DoctrineCacheStorage(
      new ChainCache([
        new ArrayCache(),
        new FilesystemCache('/tmp/'),
      ])
    )
  )
), 'cache');
```

## Laravel cache
You can use a cache with Laravel, e.g. Redis, Memcache etc.:
```php
[...]
use Illuminate\Support\Facades\Cache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\LaravelCacheStorage;

[...]

$stack->push(
  new CacheMiddleware(
    new PrivateCacheStrategy(
      new LaravelCacheStorage(
        Cache::store('redis')
      )
    )
  ),
  'cache'
);
```

## Flysystem
```php
[...]
use League\Flysystem\Adapter\Local;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;

[...]

$stack->push(
  new CacheMiddleware(
    new PrivateCacheStrategy(
      new FlysystemStorage(
        new Local('/path/to/cache')
      )
    )
  ), 
  'cache'
);
```

## Public and shared
It's possible to add a public shared cache to the stack:
```php
[...]
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\PredisCache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;

[...]
// Private caching
$stack->push(
  new CacheMiddleware(
    new PrivateCacheStrategy(
      new DoctrineCacheStorage(
        new FilesystemCache('/tmp/')
      )
    )
  ), 
  'private-cache'
);

// Public caching
$stack->push(
  new CacheMiddleware(
    new PublicCacheStrategy(
      new DoctrineCacheStorage(
        new PredisCache(
          new Predis\Client('tcp://10.0.0.1:6379')
        )
      )
    )
  ), 
  'shared-cache'
);
```
