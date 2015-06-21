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
     * @var bool
     */
    protected $staleIfError;


    /**
     * @param ResponseInterface $response
     * @param \DateTime $staleAt
     * @param bool|null $staleIfError if null, detected with the headers (RFC 5861)
     */
    public function __construct(ResponseInterface $response, \DateTime $staleAt, $staleIfError = null)
    {
        $this->response = $response;
        $this->staleAt  = $staleAt;

        if ($staleIfError === null) {
            $this->staleIfError =
                $response->hasHeader("Cache-Control")
                && in_array('stale-if-error', $response->getHeader("Cache-Control"))
            ;
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
        static $timestamp = $this->staleAt->getTimestamp();

        return $timestamp < (new \DateTime())->getTimestamp();
    }

    /**
     * @return bool
     */
    public function serveStaleIfError()
    {
        return (bool)$this->staleIfError;
    }

}