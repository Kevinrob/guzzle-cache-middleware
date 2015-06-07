# guzzle-cache-middleware
A (future) HTTP Cache for Guzzle 6. It's a simple Middleware to be added in the HandlerStack.
It's in development.

`composer require kevinrob/guzzle-cache-middleware:dev-master`

# Why?
Performance. It's very common to do some HTTP calls to an API for rendering a page and it takes times to do it.

# How?
With a simple Middleware added at the top of the `HandlerStack` of Guzzle6.

```php
// Create default HandlerStack
$stack = HandlerStack::create();

// Add this middleware to the top with `push`
$stack->push(\Kevinrob\GuzzleCache\CacheMiddleware::getMiddleware());

// Initialize the client with the handler option
$client = new Client(['handler' => $stack]);
```
