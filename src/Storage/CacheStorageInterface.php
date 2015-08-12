<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 12.08.2015
 * Time: 19:07
 */

namespace Kevinrob\GuzzleCache\Storage;


use Kevinrob\GuzzleCache\CacheEntry;

interface CacheStorageInterface
{

    /**
     * @param string $key
     * @return CacheEntry|null the data or false
     */
    public function fetch($key);

    /**
     * @param string $key
     * @param CacheEntry $data
     * @return bool
     */
    public function save($key, CacheEntry $data);

}