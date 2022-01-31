<?php

namespace Kevinrob\GuzzleCache\Tests;

use http\Message\Body;
use Kevinrob\GuzzleCache\BodyStore;
use PHPUnit\Framework\TestCase;

class BodyStoreTest extends TestCase
{
    public function testBodyStoreReturnsAllContentIfAskedLengthIsGreaterThanAvailable()
    {
        $str = 'Not so long';
        $bodyStore = new BodyStore($str);
        $this->assertEquals($str, $bodyStore(PHP_INT_MAX));
    }

    public function testBodyStoreReturnsFalseIsAllHasBeenRead()
    {
        $str = 'Not so long';
        $bodyStore = new BodyStore($str);
        $bodyStore(PHP_INT_MAX);
        $this->assertFalse($bodyStore(1));
    }

    public function testBodyStoreCanReadAllContentWhenIteratedEnough()
    {
        $originalString = <<<EOF
            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
EOF;
        $bodyStore = new BodyStore($originalString);

        $got = '';
        while ($str = $bodyStore(1)) {
            $got .= $str;
        }
        $this->assertEquals($originalString, $got);
    }

    public function testBodyStoreReturnsEmptyStringWhenAsking0()
    {
        $str = 'Not so long';
        $bodyStore = new BodyStore($str);
        $this->assertEquals('', $bodyStore(0));
    }
}
