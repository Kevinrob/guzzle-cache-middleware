<?php

namespace Kevinrob\GuzzleCache;


use Psr\Http\Message\ResponseInterface;

class CacheEntry
{

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * This field is only use for serialize.
     * Response::body is a stream and can't be serialized.
     *
     * @var string
     */
    protected $responseBody;

    /**
     * @var \DateTime
     */
    protected $staleAt;

    /**
     * @var \DateTime
     */
    protected $staleIfErrorTo;

    /**
     * @var \DateTime
     */
    protected $staleWhileRevalidateTo;

    /**
     * Cached timestamp of staleAt variable
     * @var int
     */
    private $timestampStale;


    /**
     * @param ResponseInterface $response
     * @param \DateTime $staleAt
     * @param \DateTime|null $staleIfErrorTo if null, detected with the headers (RFC 5861)
     * @param \DateTime $staleWhileRevalidateTo
     */
    public function __construct(
        ResponseInterface $response,
        \DateTime $staleAt,
        \DateTime $staleIfErrorTo = null,
        \DateTime $staleWhileRevalidateTo = null
    ) {
        $this->response = $response;
        $this->staleAt  = $staleAt;

        $values = new KeyValueHttpHeader($response->getHeader("Cache-Control"));

        if ($staleIfErrorTo === null && $values->has('stale-if-error')) {
            $this->staleIfErrorTo = new \DateTime('+' . $values->get('stale-if-error') . 'seconds');
        } else {
            $this->staleIfErrorTo = $staleIfErrorTo;
        }

        if ($staleWhileRevalidateTo === null && $values->has('stale-while-revalidate')) {
            $this->staleWhileRevalidateTo = new \DateTime('+' . $values->get('stale-while-revalidate') . 'seconds');
        } else {
            $this->staleWhileRevalidateTo = $staleWhileRevalidateTo;
        }
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return \DateTime
     */
    public function getStaleAt()
    {
        return $this->staleAt;
    }

    /**
     * @return bool
     */
    public function isFresh()
    {
        return !$this->isStale();
    }

    /**
     * @return bool
     */
    public function isStale()
    {
        // This object is immutable
        if ($this->timestampStale === null) {
            $this->timestampStale = $this->staleAt->getTimestamp();
        }

        return $this->timestampStale < (new \DateTime())->getTimestamp();
    }

    /**
     * @return bool
     */
    public function serveStaleIfError()
    {
        return $this->staleIfErrorTo !== null
            && $this->staleIfErrorTo->getTimestamp() >= (new \DateTime())->getTimestamp();
    }

    /**
     * @return bool
     */
    public function staleWhileValidate()
    {
        return $this->staleWhileRevalidateTo !== null
            && $this->staleWhileRevalidateTo->getTimestamp() >= (new \DateTime())->getTimestamp();
    }

    /**
     * @return bool
     */
    public function hasValidationInformation()
    {
        return $this->response->hasHeader("Etag") || $this->response->hasHeader("Last-Modified");
    }

    public function __sleep()
    {
        // Stream/Resource can't be serialized... So we copy the content
        if ($this->response !== null) {
            $this->responseBody = (string)$this->response->getBody();
            $this->response->getBody()->rewind();
        }

        return array_keys(get_object_vars($this));
    }

    public function __wakeup()
    {
        // We re-create the stream of the response
        if ($this->response !== null) {
            $this->response = $this->response
                ->withBody(
                    \GuzzleHttp\Psr7\stream_for($this->responseBody)
                );
        }
    }

}
