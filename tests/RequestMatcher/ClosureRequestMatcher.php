<?php

namespace Kevinrob\GuzzleCache\Tests\RequestMatcher;

use Closure;
use Kevinrob\GuzzleCache\Strategy\Delegate\RequestMatcherInterface;
use Psr\Http\Message\RequestInterface;

class ClosureRequestMatcher implements RequestMatcherInterface
{
    /**
     * @var Closure
     */
    private $closure;

    /**
     * CallbackRequestMatcher constructor.
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * @inheritDoc
     */
    public function matches(RequestInterface $request)
    {
        $closure = $this->closure;
        return (bool) $closure($request);
    }
}