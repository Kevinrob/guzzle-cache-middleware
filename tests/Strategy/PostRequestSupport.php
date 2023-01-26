<?php

declare(strict_types=1);

namespace Kevinrob\GuzzleCache\Tests\Strategy;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\Storage\VolatileRuntimeStorage;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;

class PostRequestSupport extends \PHPUnit\Framework\TestCase
{
    private const HEADERS = ['Cache-Control' => 'max-age=1000000, max-stale=1000000'];
    private const REQUEST_BODY_BTC = '{"currency": "BTC"}';
    private const REQUEST_BODY_ETH= '{"currency": "ETH"}';
    private const RESPONSE_BODY = '{"value": "0.2"}';

    public function strategyProvider():\Generator
    {
        yield [new PrivateCacheStrategy(new VolatileRuntimeStorage())];
        yield [new GreedyCacheStrategy(new VolatileRuntimeStorage(), 100)];
    }

    /**
     * @dataProvider strategyProvider
     */
    public function testItShouldReturnCacheWhenBodyIsSame(CacheStrategyInterface $storage):void
    {
        $request = new Request('post', 'url', self::HEADERS, self::REQUEST_BODY_BTC);
        $response = new Response(200, [], self::RESPONSE_BODY);
        $storage->cache($request, $response);
        self::assertNotNull($storage->fetch($request));
    }
    /**
     * @dataProvider strategyProvider
     */
    public function testItShouldNotHaveCacheForRequestWithDifferntBody(CacheStrategyInterface $storage):void
    {
        $request = new Request('post', 'url', self::HEADERS, self::REQUEST_BODY_BTC);
        $requestDifferntBody = new Request('post', 'url', self::HEADERS, self::REQUEST_BODY_ETH);
        $response = new Response(200, [], self::RESPONSE_BODY);
        $storage->cache($request, $response);
        self::assertNull($storage->fetch($requestDifferntBody));
    }
}
