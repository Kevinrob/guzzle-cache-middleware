<?php

namespace Kevinrob\GuzzleCache;

use Psr\Http\Message\StreamInterface;

/**
 * A message body backed by a plain string, safe for native PHP serialization.
 *
 * PSR-7 stream implementations can't be relied on to survive `serialize()`
 * (guzzlehttp/psr7 3 streams throw), so `CacheEntry` swaps message bodies for
 * this stream before a cache entry is serialized.
 *
 * @internal don't use it in your project.
 */
class SerializableBodyStream implements StreamInterface
{
    /**
     * @var string
     */
    private $body;

    /**
     * @var int
     */
    private $position = 0;

    public function __construct(string $body)
    {
        $this->body = $body;
    }

    public function __serialize(): array
    {
        return ['body' => $this->body];
    }

    public function __unserialize(array $data): void
    {
        if (!isset($data['body']) || !is_string($data['body'])) {
            throw new \InvalidArgumentException('Corrupted body stream');
        }

        $this->body = $data['body'];
        $this->position = 0;
    }

    public function __toString(): string
    {
        return $this->body;
    }

    public function close(): void
    {
        $this->body = '';
        $this->position = 0;
    }

    public function detach()
    {
        $this->close();

        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->body);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->body);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $position = $offset;
        } elseif ($whence === SEEK_CUR) {
            $position = $this->position + $offset;
        } elseif ($whence === SEEK_END) {
            $position = strlen($this->body) + $offset;
        } else {
            throw new \InvalidArgumentException('Invalid whence: '.$whence);
        }

        if ($position < 0) {
            throw new \RuntimeException('Cannot seek to a negative position');
        }

        $this->position = $position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new \RuntimeException('Cannot write to a '.self::class);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read($length): string
    {
        if ($length <= 0 || $this->eof()) {
            return '';
        }

        $data = substr($this->body, $this->position, $length);
        $this->position += strlen($data);

        return $data;
    }

    public function getContents(): string
    {
        $contents = substr($this->body, $this->position);
        $this->position = strlen($this->body);

        return $contents;
    }

    public function getMetadata($key = null)
    {
        return $key !== null ? null : [];
    }
}
