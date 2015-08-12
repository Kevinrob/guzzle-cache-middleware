# guzzle-cache-middleware

[![Latest Stable Version](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/v/stable)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![Total Downloads](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/downloads)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![License](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/license)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![Build Status](https://travis-ci.org/Kevinrob/guzzle-cache-middleware.svg)](https://travis-ci.org/Kevinrob/guzzle-cache-middleware)  
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Kevinrob/guzzle-cache-middleware/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Kevinrob/guzzle-cache-middleware/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/077ec9d6-9362-43be-83c9-cf1db2c9c802/mini.png)](https://insight.sensiolabs.com/projects/077ec9d6-9362-43be-83c9-cf1db2c9c802) [![Dependency Status](https://www.versioneye.com/php/kevinrob:guzzle-cache-middleware/badge.png)](https://www.versioneye.com/php/kevinrob:guzzle-cache-middleware)


A HTTP Cache for [Guzzle](https://github.com/guzzle/guzzle) 6. It's a simple Middleware to be added in the HandlerStack.
This project is under development but it's already functional.

## Installation

`composer require kevinrob/guzzle-cache-middleware:~0.5`

or add it the your `composer.json` and make a `composer update kevinrob/guzzle-cache-middleware`.

# Dependencies
Currently it depends on and works only with [Doctrine\Cache](https://github.com/doctrine/cache) as the actual caching backend.

# Why?
Performance. It's very common to do some HTTP calls to an API for rendering a page and it takes times to do it.

# How?
With a simple Middleware added at the top of the `HandlerStack` of Guzzle6.

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache;

// Create default HandlerStack
$stack = HandlerStack::create();

// Add this middleware to the top with `push`
$stack->push(new CacheMiddleware(), 'cache');

// Initialize the client with the handler option
$client = new Client(['handler' => $stack]);
```

You can use a custom Cache with:
```php
[...]
use Doctrine\Common\Cache;

[...]
$stack->push(new CacheMiddleware(new PrivateCache(new FilesystemCache('/tmp/'))), 'cache');
```

You can use `ChainCache` for using multiple `CacheProvider`. With that provider, you have to sort the different cache from the faster to the slower. Like that, you can have a very fast cache.
```php
[...]
use Doctrine\Common\Cache;

[...]
$stack->push(new CacheMiddleware(
  new PrivateCache(
    new ChainCache([
      new ArrayCache(),
      new ApcCache(),
      new FileCache('/tmp/'),
    ])
  )
), 'cache');
```
