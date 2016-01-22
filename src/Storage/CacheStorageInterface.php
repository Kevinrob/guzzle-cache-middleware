<?php

namespace Kevinrob\GuzzleCache\Storage;

use Kevinrob\GuzzleCache\CacheEntry;

interface CacheStorageInterface
{
    /**
     * @param string $key
     *
     * @return CacheEntry|null the data or false
     */
    public function fetch($key);

    /**
     * @param string     $key
     * @param CacheEntry $data
     *
     * @return bool
     */
    public function save($key, CacheEntry $data);

    /**
     * Invalidates the Cache entry with the given key.
     *
     * @param string
     *
     * @return bool
     */
    public function delete($key);
}
