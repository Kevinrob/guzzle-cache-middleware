<?php

namespace Kevinrob\GuzzleCache\Tests\Storage;

use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class FlysystemStorageTest extends TestCase
{
    use ProphecyTrait;

    public function testFetchReturnsNullOnFilesystemException()
    {
        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->fileExists('testKey')->willReturn(true)->shouldBeCalled();
        $filesystem->read('testKey')->willThrow(new UnableToReadFile('Mocked read failure'))->shouldBeCalled();

        $storage = new FlysystemStorage($this->prophesize(FilesystemAdapter::class)->reveal());
        // Inject the mocked Filesystem object
        $reflection = new \ReflectionClass($storage);
        $property = $reflection->getProperty('filesystem');
        $property->setAccessible(true);
        $property->setValue($storage, $filesystem->reveal());

        $this->assertNull($storage->fetch('testKey'));
    }

    public function testSaveReturnsFalseOnFilesystemException()
    {
        // Create a real CacheEntry with a dummy request and response to ensure it can be serialized
        $dummyRequest = new \GuzzleHttp\Psr7\Request('GET', 'test-uri');
        $dummyResponse = new \GuzzleHttp\Psr7\Response(200, [], 'test body');
        $cacheEntry = new CacheEntry($dummyRequest, $dummyResponse, new \DateTime('+1 hour'));

        $filesystem = $this->prophesize(Filesystem::class);
        // Prophesize with the actual serialized object
        $filesystem->write('testKey', \serialize($cacheEntry))
            ->willThrow(new UnableToWriteFile('Mocked write failure'))
            ->shouldBeCalled();

        $storage = new FlysystemStorage($this->prophesize(FilesystemAdapter::class)->reveal());
        // Inject the mocked Filesystem object
        $reflection = new \ReflectionClass($storage);
        $property = $reflection->getProperty('filesystem');
        $property->setAccessible(true);
        $property->setValue($storage, $filesystem->reveal());

        $this->assertFalse($storage->save('testKey', $cacheEntry));
    }

    public function testDeleteReturnsFalseOnFilesystemException()
    {
        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->delete('testKey')->willThrow(new UnableToDeleteFile('Mocked delete failure'))->shouldBeCalled();

        $storage = new FlysystemStorage($this->prophesize(FilesystemAdapter::class)->reveal());
        // Inject the mocked Filesystem object
        $reflection = new \ReflectionClass($storage);
        $property = $reflection->getProperty('filesystem');
        $property->setAccessible(true);
        $property->setValue($storage, $filesystem->reveal());

        $this->assertFalse($storage->delete('testKey'));
    }

    // It might be good to also test the constructor if FilesystemAdapter is directly used
    // or if the Filesystem object construction within FlysystemStorage needs specific adapter behavior.
    // For now, focusing on the core fetch/save/delete exception handling.

    // Test for fetch when file does not exist - should return null without exception
    public function testFetchReturnsNullWhenFileDoesNotExist()
    {
        $filesystem = $this->prophesize(Filesystem::class);
        $filesystem->fileExists('nonExistentKey')->willReturn(false)->shouldBeCalled();
        // read should not be called
        $filesystem->read('nonExistentKey')->shouldNotBeCalled();


        $storage = new FlysystemStorage($this->prophesize(FilesystemAdapter::class)->reveal());
        $reflection = new \ReflectionClass($storage);
        $property = $reflection->getProperty('filesystem');
        $property->setAccessible(true);
        $property->setValue($storage, $filesystem->reveal());

        $this->assertNull($storage->fetch('nonExistentKey'));
    }
}
