<?php

namespace Kevinrob\GuzzleCache\Storage;

use Psr\Cache\CacheItemPoolInterface;
use Kevinrob\GuzzleCache\CacheEntry;

class Psr6CacheStorage implements CacheStorageInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    protected $cachePool;

    /**
     * @param CacheItemPoolInterface $cachePool
     */
    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($key)
    {
        $item = $this->cachePool->getItem($key);

        if ($item->isHit()) {
            $cache = unserialize($item->get());

            if ($cache instanceof CacheEntry) {
                return $cache;
            }
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function save($key, CacheEntry $data)
    {
        $item = $this->cachePool->getItem($key);
        $item->set(serialize($data));

        return $this->cachePool->save($item);
    }
}
