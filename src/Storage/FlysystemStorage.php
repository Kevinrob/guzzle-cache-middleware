<?php

namespace Kevinrob\GuzzleCache\Storage;

use Kevinrob\GuzzleCache\CacheEntry;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;

class FlysystemStorage implements CacheStorageInterface
{

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct(FilesystemAdapter $adapter)
    {
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * @inheritdoc
     */
    public function fetch($key)
    {
        if ($this->filesystem->fileExists($key)) {
            // The file exists, read it!
            try {
                $data = @unserialize(
                    $this->filesystem->read($key),
                    ['allowed_classes' => CacheEntry::getAllowedClasses()]
                );

                if ($data instanceof CacheEntry) {
                    return $data;
                }
            } catch (\Throwable $e) {
                // If unserialize fails (e.g. InvalidArgumentException from corrupted cache)
                return null;
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function save($key, CacheEntry $data)
    {
      try {
        $this->filesystem->write($key, serialize($data));
        return true;
      } catch (FilesystemException $e) {
        return false;
      }
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            return $this->filesystem->delete($key);
        } catch (FilesystemException $ex) {
            return true;
        }
    }
}
