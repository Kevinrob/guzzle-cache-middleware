<?php

namespace Kevinrob\GuzzleCache;

/**
 *
 * This object is only meant to provide a callable to `GuzzleHttp\Psr7\PumpStream`.
 * It is only kept so cache entries written by older versions of this library
 * can still be unserialized (new entries use SerializableBodyStream).
 *
 * @internal don't use it in your project.
 */
class BodyStore
{
    private $body;

    private $read = 0;

    private $toRead;

    public function __construct(string $body)
    {
        $this->body = $body;
        $this->toRead = strlen($this->body);
    }

    /**
     * @param int $length
     * @return false|string
     */
    public function __invoke(int $length)
    {
        if ($this->toRead <= 0) {
            return false;
        }

        $length = min($length, $this->toRead);

        $body = substr(
            $this->body,
            $this->read,
            $length
        );
        $this->toRead -= $length;
        $this->read += $length;
        return $body;
    }
}
