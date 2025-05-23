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
            try {
                // The file exist, read it!
                $data = @unserialize(
                    $this->filesystem->read($key)
                );

                if ($data instanceof CacheEntry) {
                    return $data;
                }
            } catch (FilesystemException $e) {
                // In case of error, act as if the file didn't exist
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
            $this->filesystem->delete($key);
            return true;
        } catch (FilesystemException $ex) {
            return false;
        }
    }
}
