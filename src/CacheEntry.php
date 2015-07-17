<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 21.06.2015
 * Time: 16:19
 */

namespace Kevinrob\GuzzleCache;


use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;

class CacheEntry
{

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
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

        if ($staleIfErrorTo === null) {
            $headersCacheControl = $response->getHeader("Cache-Control");

            if (!in_array("must-revalidate", $headersCacheControl)) {
                foreach ($headersCacheControl as $directive) {
                    $matches = [];
                    if (preg_match('/^stale-if-error=([0-9]*)$/', $directive, $matches)) {
                        $this->staleIfErrorTo = new \DateTime('+' . $matches[1] . 'seconds');
                        break;
                    }
                }
            }
        } else {
            $this->staleIfErrorTo = $staleIfErrorTo;
        }

        if ($staleWhileRevalidateTo === null) {
            foreach ($response->getHeader("Cache-Control") as $directive) {
                $matches = [];
                if (preg_match('/^stale-while-revalidate=([0-9]*)$/', $directive, $matches)) {
                    $this->staleWhileRevalidateTo = new \DateTime('+' . $matches[1] . 'seconds');
                    break;
                }
            }
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
        if ($this->response !== null) {
            $this->responseBody = (string)$this->response->getBody();
        }

        return array_keys(get_object_vars($this));
    }

    public function __wakeup()
    {
        if ($this->response !== null) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $this->responseBody);
            rewind($stream);

            $this->response = $this->response
                ->withBody(new Stream($stream));
        }
    }

}
