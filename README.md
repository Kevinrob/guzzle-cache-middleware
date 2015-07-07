# guzzle-cache-middleware

[![Latest Stable Version](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/v/stable)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![Total Downloads](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/downloads)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![License](https://poser.pugx.org/kevinrob/guzzle-cache-middleware/license)](https://packagist.org/packages/kevinrob/guzzle-cache-middleware) [![Build Status](https://travis-ci.org/Kevinrob/guzzle-cache-middleware.svg)](https://travis-ci.org/Kevinrob/guzzle-cache-middleware)  
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Kevinrob/guzzle-cache-middleware/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Kevinrob/guzzle-cache-middleware/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/077ec9d6-9362-43be-83c9-cf1db2c9c802/mini.png)](https://insight.sensiolabs.com/projects/077ec9d6-9362-43be-83c9-cf1db2c9c802)

A HTTP Cache for [Guzzle](https://github.com/guzzle/guzzle) 6. It's a simple Middleware to be added in the HandlerStack.
This project is under development but it's already functional.

`composer require kevinrob/guzzle-cache-middleware:~0.3`

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
$stack->push(CacheMiddleware::getMiddleware(), 'cache');

// Initialize the client with the handler option
$client = new Client(['handler' => $stack]);
```

You can use a custom Cache with:
```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache;
use Doctrine\Common\Cache;

// Create default HandlerStack
$stack = HandlerStack::create();

// Add this middleware to the top with `push`
$stack->push(CacheMiddleware::getMiddleware(new PrivateCache(new FileCache('/tmp/')), 'cache');

// Initialize the client with the handler option
$client = new Client(['handler' => $stack]);
```
