<?php
/**
 * Created by IntelliJ IDEA.
 * User: Kevin
 * Date: 12.08.2015
 * Time: 19:13
 */

namespace Kevinrob\GuzzleCache\Storage;


use Doctrine\Common\Cache\Cache;
use Kevinrob\GuzzleCache\CacheEntry;

class DoctrineCacheWrapper implements CacheStorageInterface
{

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @inheritdoc
     */
    public function fetch($key)
    {
        try {
            $cache = unserialize($this->cache->fetch($key));
            if ($cache instanceof CacheEntry) {
                return $cache;
            }
        } catch (\Exception $ignored) {
            return null;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function save($key, CacheEntry $data)
    {
        try {
            $lifeTime = $data->getTTL();
            if ($lifeTime >= 0) {
                return $this->cache->save(
                    $key,
                    serialize($data),
                    $lifeTime
                );
            }
        } catch (\Exception $ignored) { }

        return false;
    }

}