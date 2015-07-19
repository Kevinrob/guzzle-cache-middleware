<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 19.07.2015
 * Time: 16:21
 */

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Psr7\Response;

class KeyValueHttpHeaderTest extends \PHPUnit_Framework_TestCase
{

    public function testBase()
    {
        $response = new Response(200, [
            'Cache-Control' => [
                'max-age = 120',
                ' stale-while-revalidate=  60 ',
                '  private ',
                'zero=0',
                'nothing = ',
                'false = false',
            ]
        ]);

        $values = new KeyValueHttpHeader($response->getHeader('Cache-Control'));

        $this->assertTrue($values->has('max-age'));
        $this->assertTrue($values->has('stale-while-revalidate'));
        $this->assertTrue($values->has('private'));
        $this->assertTrue($values->has('zero'));
        $this->assertTrue($values->has('nothing'));
        $this->assertTrue($values->has('false'));

        $this->assertEquals(120, $values->get('max-age'));
        $this->assertEquals(60, $values->get('stale-while-revalidate'));
        $this->assertEquals(0, $values->get('zero'));
        $this->assertEquals('', $values->get('nothing'));
        $this->assertEquals('false', $values->get('false'));
    }

}