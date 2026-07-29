<?php

namespace Kevinrob\GuzzleCache\Tests;

use GuzzleHttp\Psr7\PumpStream;
use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Ensures the serialized cache entry format stays readable across the
 * guzzlehttp/psr7 major versions supported by this library.
 *
 * Both fixtures were generated with guzzlehttp/psr7 2.x from the same
 * request/response pair:
 * - CURRENT_FORMAT_FIXTURE with the SerializableBodyStream-based format,
 * - LEGACY_FORMAT_FIXTURE with the PumpStream-based format written by
 *   older versions of this library.
 */
class SerializationCompatibilityTest extends TestCase
{
    private const CURRENT_FORMAT_FIXTURE = 'TzozMToiS2V2aW5yb2JcR3V6emxlQ2FjaGVcQ2FjaGVFbnRyeSI6Nzp7czo3OiJyZXF1ZXN0IjtPOjIzOiJHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdCI6Nzp7czozMToiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AG1ldGhvZCI7czozOiJHRVQiO3M6Mzg6IgBHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdAByZXF1ZXN0VGFyZ2V0IjtOO3M6Mjg6IgBHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdAB1cmkiO086MTk6Ikd1enpsZUh0dHBcUHNyN1xVcmkiOjc6e3M6Mjc6IgBHdXp6bGVIdHRwXFBzcjdcVXJpAHNjaGVtZSI7czo1OiJodHRwcyI7czoyOToiAEd1enpsZUh0dHBcUHNyN1xVcmkAdXNlckluZm8iO3M6MDoiIjtzOjI1OiIAR3V6emxlSHR0cFxQc3I3XFVyaQBob3N0IjtzOjExOiJleGFtcGxlLmNvbSI7czoyNToiAEd1enpsZUh0dHBcUHNyN1xVcmkAcG9ydCI7TjtzOjI1OiIAR3V6emxlSHR0cFxQc3I3XFVyaQBwYXRoIjtzOjU6Ii90ZXN0IjtzOjI2OiIAR3V6emxlSHR0cFxQc3I3XFVyaQBxdWVyeSI7czowOiIiO3M6Mjk6IgBHdXp6bGVIdHRwXFBzcjdcVXJpAGZyYWdtZW50IjtzOjA6IiI7fXM6MzI6IgBHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdABoZWFkZXJzIjthOjE6e3M6NDoiSG9zdCI7YToxOntpOjA7czoxMToiZXhhbXBsZS5jb20iO319czozNjoiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AGhlYWRlck5hbWVzIjthOjE6e3M6NDoiaG9zdCI7czo0OiJIb3N0Ijt9czozMzoiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AHByb3RvY29sIjtzOjM6IjEuMSI7czozMToiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AHN0cmVhbSI7Tzo0MzoiS2V2aW5yb2JcR3V6emxlQ2FjaGVcU2VyaWFsaXphYmxlQm9keVN0cmVhbSI6MTp7czo0OiJib2R5IjtzOjExOiJTYW1wbGUgYm9keSI7fX1zOjg6InJlc3BvbnNlIjtPOjI0OiJHdXp6bGVIdHRwXFBzcjdcUmVzcG9uc2UiOjY6e3M6Mzg6IgBHdXp6bGVIdHRwXFBzcjdcUmVzcG9uc2UAcmVhc29uUGhyYXNlIjtzOjI6Ik9LIjtzOjM2OiIAR3V6emxlSHR0cFxQc3I3XFJlc3BvbnNlAHN0YXR1c0NvZGUiO2k6MjAwO3M6MzM6IgBHdXp6bGVIdHRwXFBzcjdcUmVzcG9uc2UAaGVhZGVycyI7YToyOntzOjEzOiJDYWNoZS1Db250cm9sIjthOjE6e2k6MDtzOjEwOiJtYXgtYWdlPTYwIjt9czo0OiJFdGFnIjthOjE6e2k6MDtzOjU6IiJmb28iIjt9fXM6Mzc6IgBHdXp6bGVIdHRwXFBzcjdcUmVzcG9uc2UAaGVhZGVyTmFtZXMiO2E6Mjp7czoxMzoiY2FjaGUtY29udHJvbCI7czoxMzoiQ2FjaGUtQ29udHJvbCI7czo0OiJldGFnIjtzOjQ6IkV0YWciO31zOjM0OiIAR3V6emxlSHR0cFxQc3I3XFJlc3BvbnNlAHByb3RvY29sIjtzOjM6IjEuMSI7czozMjoiAEd1enpsZUh0dHBcUHNyN1xSZXNwb25zZQBzdHJlYW0iO086NDM6Iktldmlucm9iXEd1enpsZUNhY2hlXFNlcmlhbGl6YWJsZUJvZHlTdHJlYW0iOjE6e3M6NDoiYm9keSI7czoxMjoiVGVzdCBjb250ZW50Ijt9fXM6Nzoic3RhbGVBdCI7TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAzNi0wMS0wMSAwMDowMDowMC4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MTtzOjg6InRpbWV6b25lIjtzOjY6IiswMDowMCI7fXM6MTQ6InN0YWxlSWZFcnJvclRvIjtOO3M6MjI6InN0YWxlV2hpbGVSZXZhbGlkYXRlVG8iO047czoxMToiZGF0ZUNyZWF0ZWQiO086MTc6IkRhdGVUaW1lSW1tdXRhYmxlIjozOntzOjQ6ImRhdGUiO3M6MjY6IjIwMjYtMDEtMDEgMDA6MDA6MDAuMDAwMDAwIjtzOjEzOiJ0aW1lem9uZV90eXBlIjtpOjE7czo4OiJ0aW1lem9uZSI7czo2OiIrMDA6MDAiO31zOjE0OiJ0aW1lc3RhbXBTdGFsZSI7Tjt9';

    private const LEGACY_FORMAT_FIXTURE = 'TzozMToiS2V2aW5yb2JcR3V6emxlQ2FjaGVcQ2FjaGVFbnRyeSI6Nzp7czo3OiJyZXF1ZXN0IjtPOjIzOiJHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdCI6Nzp7czozMToiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AG1ldGhvZCI7czozOiJHRVQiO3M6Mzg6IgBHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdAByZXF1ZXN0VGFyZ2V0IjtOO3M6Mjg6IgBHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdAB1cmkiO086MTk6Ikd1enpsZUh0dHBcUHNyN1xVcmkiOjc6e3M6Mjc6IgBHdXp6bGVIdHRwXFBzcjdcVXJpAHNjaGVtZSI7czo1OiJodHRwcyI7czoyOToiAEd1enpsZUh0dHBcUHNyN1xVcmkAdXNlckluZm8iO3M6MDoiIjtzOjI1OiIAR3V6emxlSHR0cFxQc3I3XFVyaQBob3N0IjtzOjExOiJleGFtcGxlLmNvbSI7czoyNToiAEd1enpsZUh0dHBcUHNyN1xVcmkAcG9ydCI7TjtzOjI1OiIAR3V6emxlSHR0cFxQc3I3XFVyaQBwYXRoIjtzOjU6Ii90ZXN0IjtzOjI2OiIAR3V6emxlSHR0cFxQc3I3XFVyaQBxdWVyeSI7czowOiIiO3M6Mjk6IgBHdXp6bGVIdHRwXFBzcjdcVXJpAGZyYWdtZW50IjtzOjA6IiI7fXM6MzI6IgBHdXp6bGVIdHRwXFBzcjdcUmVxdWVzdABoZWFkZXJzIjthOjE6e3M6NDoiSG9zdCI7YToxOntpOjA7czoxMToiZXhhbXBsZS5jb20iO319czozNjoiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AGhlYWRlck5hbWVzIjthOjE6e3M6NDoiaG9zdCI7czo0OiJIb3N0Ijt9czozMzoiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AHByb3RvY29sIjtzOjM6IjEuMSI7czozMToiAEd1enpsZUh0dHBcUHNyN1xSZXF1ZXN0AHN0cmVhbSI7TzoyNjoiR3V6emxlSHR0cFxQc3I3XFB1bXBTdHJlYW0iOjU6e3M6MzQ6IgBHdXp6bGVIdHRwXFBzcjdcUHVtcFN0cmVhbQBzb3VyY2UiO086MzA6Iktldmlucm9iXEd1enpsZUNhY2hlXEJvZHlTdG9yZSI6Mzp7czozNjoiAEtldmlucm9iXEd1enpsZUNhY2hlXEJvZHlTdG9yZQBib2R5IjtzOjExOiJTYW1wbGUgYm9keSI7czozNjoiAEtldmlucm9iXEd1enpsZUNhY2hlXEJvZHlTdG9yZQByZWFkIjtpOjA7czozODoiAEtldmlucm9iXEd1enpsZUNhY2hlXEJvZHlTdG9yZQB0b1JlYWQiO2k6MTE7fXM6MzI6IgBHdXp6bGVIdHRwXFBzcjdcUHVtcFN0cmVhbQBzaXplIjtpOjExO3M6MzU6IgBHdXp6bGVIdHRwXFBzcjdcUHVtcFN0cmVhbQB0ZWxsUG9zIjtpOjA7czozNjoiAEd1enpsZUh0dHBcUHNyN1xQdW1wU3RyZWFtAG1ldGFkYXRhIjthOjA6e31zOjM0OiIAR3V6emxlSHR0cFxQc3I3XFB1bXBTdHJlYW0AYnVmZmVyIjtPOjI4OiJHdXp6bGVIdHRwXFBzcjdcQnVmZmVyU3RyZWFtIjoyOntzOjMzOiIAR3V6emxlSHR0cFxQc3I3XEJ1ZmZlclN0cmVhbQBod20iO2k6MTYzODQ7czozNjoiAEd1enpsZUh0dHBcUHNyN1xCdWZmZXJTdHJlYW0AYnVmZmVyIjtzOjA6IiI7fX19czo4OiJyZXNwb25zZSI7TzoyNDoiR3V6emxlSHR0cFxQc3I3XFJlc3BvbnNlIjo2OntzOjM4OiIAR3V6emxlSHR0cFxQc3I3XFJlc3BvbnNlAHJlYXNvblBocmFzZSI7czoyOiJPSyI7czozNjoiAEd1enpsZUh0dHBcUHNyN1xSZXNwb25zZQBzdGF0dXNDb2RlIjtpOjIwMDtzOjMzOiIAR3V6emxlSHR0cFxQc3I3XFJlc3BvbnNlAGhlYWRlcnMiO2E6Mjp7czoxMzoiQ2FjaGUtQ29udHJvbCI7YToxOntpOjA7czoxMDoibWF4LWFnZT02MCI7fXM6NDoiRXRhZyI7YToxOntpOjA7czo1OiIiZm9vIiI7fX1zOjM3OiIAR3V6emxlSHR0cFxQc3I3XFJlc3BvbnNlAGhlYWRlck5hbWVzIjthOjI6e3M6MTM6ImNhY2hlLWNvbnRyb2wiO3M6MTM6IkNhY2hlLUNvbnRyb2wiO3M6NDoiZXRhZyI7czo0OiJFdGFnIjt9czozNDoiAEd1enpsZUh0dHBcUHNyN1xSZXNwb25zZQBwcm90b2NvbCI7czozOiIxLjEiO3M6MzI6IgBHdXp6bGVIdHRwXFBzcjdcUmVzcG9uc2UAc3RyZWFtIjtPOjI2OiJHdXp6bGVIdHRwXFBzcjdcUHVtcFN0cmVhbSI6NTp7czozNDoiAEd1enpsZUh0dHBcUHNyN1xQdW1wU3RyZWFtAHNvdXJjZSI7TzozMDoiS2V2aW5yb2JcR3V6emxlQ2FjaGVcQm9keVN0b3JlIjozOntzOjM2OiIAS2V2aW5yb2JcR3V6emxlQ2FjaGVcQm9keVN0b3JlAGJvZHkiO3M6MTI6IlRlc3QgY29udGVudCI7czozNjoiAEtldmlucm9iXEd1enpsZUNhY2hlXEJvZHlTdG9yZQByZWFkIjtpOjA7czozODoiAEtldmlucm9iXEd1enpsZUNhY2hlXEJvZHlTdG9yZQB0b1JlYWQiO2k6MTI7fXM6MzI6IgBHdXp6bGVIdHRwXFBzcjdcUHVtcFN0cmVhbQBzaXplIjtpOjEyO3M6MzU6IgBHdXp6bGVIdHRwXFBzcjdcUHVtcFN0cmVhbQB0ZWxsUG9zIjtpOjA7czozNjoiAEd1enpsZUh0dHBcUHNyN1xQdW1wU3RyZWFtAG1ldGFkYXRhIjthOjA6e31zOjM0OiIAR3V6emxlSHR0cFxQc3I3XFB1bXBTdHJlYW0AYnVmZmVyIjtPOjI4OiJHdXp6bGVIdHRwXFBzcjdcQnVmZmVyU3RyZWFtIjoyOntzOjMzOiIAR3V6emxlSHR0cFxQc3I3XEJ1ZmZlclN0cmVhbQBod20iO2k6MTYzODQ7czozNjoiAEd1enpsZUh0dHBcUHNyN1xCdWZmZXJTdHJlYW0AYnVmZmVyIjtzOjA6IiI7fX19czo3OiJzdGFsZUF0IjtPOjE3OiJEYXRlVGltZUltbXV0YWJsZSI6Mzp7czo0OiJkYXRlIjtzOjI2OiIyMDM2LTAxLTAxIDAwOjAwOjAwLjAwMDAwMCI7czoxMzoidGltZXpvbmVfdHlwZSI7aToxO3M6ODoidGltZXpvbmUiO3M6NjoiKzAwOjAwIjt9czoxNDoic3RhbGVJZkVycm9yVG8iO047czoyMjoic3RhbGVXaGlsZVJldmFsaWRhdGVUbyI7TjtzOjExOiJkYXRlQ3JlYXRlZCI7TzoxNzoiRGF0ZVRpbWVJbW11dGFibGUiOjM6e3M6NDoiZGF0ZSI7czoyNjoiMjAyNi0wMS0wMSAwMDowMDowMC4wMDAwMDAiO3M6MTM6InRpbWV6b25lX3R5cGUiO2k6MTtzOjg6InRpbWV6b25lIjtzOjY6IiswMDowMCI7fXM6MTQ6InRpbWVzdGFtcFN0YWxlIjtOO30=';

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var FlysystemStorage
     */
    private $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/guzzle-cache-' . uniqid();
        if (!mkdir($this->cacheDir) && !is_dir($this->cacheDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->cacheDir));
        }

        $this->storage = new FlysystemStorage(new LocalFilesystemAdapter($this->cacheDir));
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheDir . '/entry');
        @rmdir($this->cacheDir);
        parent::tearDown();
    }

    public function testCurrentFormatEntryIsRestored()
    {
        file_put_contents($this->cacheDir . '/entry', base64_decode(self::CURRENT_FORMAT_FIXTURE));

        $entry = $this->storage->fetch('entry');

        $this->assertInstanceOf(CacheEntry::class, $entry);
        $this->assertEquals('Sample body', (string) $entry->getOriginalRequest()->getBody());
        $this->assertEquals('Test content', (string) $entry->getOriginalResponse()->getBody());
        $this->assertEquals('"foo"', $entry->getOriginalResponse()->getHeaderLine('Etag'));
    }

    public function testLegacyFormatEntryIsRestoredWithPsr7V2()
    {
        if (!self::psr7StreamsAreSerializable()) {
            $this->markTestSkipped('Legacy entries cannot be unserialized with guzzlehttp/psr7 3');
        }

        file_put_contents($this->cacheDir . '/entry', base64_decode(self::LEGACY_FORMAT_FIXTURE));

        $entry = $this->storage->fetch('entry');

        $this->assertInstanceOf(CacheEntry::class, $entry);
        $this->assertEquals('Sample body', (string) $entry->getOriginalRequest()->getBody());
        $this->assertEquals('Test content', (string) $entry->getOriginalResponse()->getBody());
    }

    public function testLegacyFormatEntryDegradesToCacheMissWithPsr7V3()
    {
        if (self::psr7StreamsAreSerializable()) {
            $this->markTestSkipped('Legacy entries are still readable with guzzlehttp/psr7 2');
        }

        file_put_contents($this->cacheDir . '/entry', base64_decode(self::LEGACY_FORMAT_FIXTURE));

        $this->assertNull($this->storage->fetch('entry'));
    }

    private static function psr7StreamsAreSerializable(): bool
    {
        // guzzlehttp/psr7 3 stream classes define __serialize()/__unserialize() to refuse native serialization
        return !method_exists(PumpStream::class, '__serialize');
    }
}
