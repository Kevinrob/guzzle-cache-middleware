<?php

/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 19.07.2015
 * Time: 16:21.
 */
namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;

class KeyValueHttpHeaderTest extends \PHPUnit_Framework_TestCase
{
    public function testBase()
    {
        $response = new Response(200, [
            'Cache-Control' => [
                'max-age=120',
                ' stale-while-revalidate=60 ',
                '  private ',
                'zero=0',
                'nothing=',
                'false=false',
                'with-comma=1,yeah="2"',
            ],
        ]);

        $values = new KeyValueHttpHeader($response->getHeader('Cache-Control'));

        $this->assertTrue($values->has('max-age'));
        $this->assertTrue($values->has('stale-while-revalidate'));
        $this->assertTrue($values->has('private'));
        $this->assertTrue($values->has('zero'));
        $this->assertTrue($values->has('nothing'));
        $this->assertTrue($values->has('false'));
        $this->assertTrue($values->has('with-comma'));
        $this->assertTrue($values->has('yeah'));

        $this->assertEquals(120, $values->get('max-age'));
        $this->assertEquals(60, $values->get('stale-while-revalidate'));
        $this->assertEquals(0, $values->get('zero'));
        $this->assertEquals('', $values->get('nothing'));
        $this->assertEquals('false', $values->get('false'));
        $this->assertEquals(1, $values->get('with-comma'));
        $this->assertEquals(2, $values->get('yeah'));
    }
}
