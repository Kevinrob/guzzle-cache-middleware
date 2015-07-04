<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 21.06.2015
 * Time: 16:19
 */

namespace Kevinrob\GuzzleCache;


use Psr\Http\Message\ResponseInterface;

class CacheEntry
{

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var \DateTime
     */
    protected $staleAt;

    /**
     * @var \DateTime
     */
    protected $staleIfErrorTo;

    /**
     * Cached timestamp of staleAt variable
     * @var int
     */
    private $timestampStale;


    /**
     * @param ResponseInterface $response
     * @param \DateTime $staleAt
     * @param \DateTime|null $staleIfErrorTo if null, detected with the headers (RFC 5861)
     */
    public function __construct(ResponseInterface $response, \DateTime $staleAt, \DateTime $staleIfErrorTo = null)
    {
        $this->response = $response;
        $this->staleAt  = $staleAt;

        if ($staleIfErrorTo === null) {
            foreach ($response->getHeader("Cache-Control") as $directive) {
                $matches = [];
                if (preg_match('/^stale-if-error=([0-9]*)$/', $directive, $matches)) {
                    $this->staleIfErrorTo = new \DateTime('+' . $matches[1] . 'seconds');
                    break;
                }
            }
        } else {
            $this->staleIfErrorTo = $staleIfErrorTo;
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
    public function hasValidationInformation()
    {
        return $this->response->hasHeader("Etag") || $this->response->hasHeader("Last-Modified");
    }

}
