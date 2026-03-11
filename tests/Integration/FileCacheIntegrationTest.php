<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests\Integration;

use LPhenom\Cache\Driver\FileCache;
use LPhenom\Storage\LocalFilesystemStorage;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for FileCache using real LocalFilesystemStorage.
 *
 * Runs against the local filesystem — no external services required.
 */
final class FileCacheIntegrationTest extends TestCase
{
    private string $storageDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/lphenom-cache-integration-' . getmypid();
        $storage          = new LocalFilesystemStorage($this->storageDir);
        $this->cache      = new FileCache($storage);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->storageDir)) {
            $files = glob($this->storageDir . '/*') ?: [];
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->storageDir);
        }
    }

    // ─── get / set ────────────────────────────────────────────────────────────

    public function testSetAndGet(): void
    {
        $this->cache->set('hello', 'world');
        self::assertSame('world', $this->cache->get('hello'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->cache->get('nonexistent'));
    }

    public function testOverwriteValue(): void
    {
        $this->cache->set('key', 'first');
        $this->cache->set('key', 'second');
        self::assertSame('second', $this->cache->get('key'));
    }

    public function testSetWithZeroTtlNeverExpires(): void
    {
        $this->cache->set('eternal', 'yes', 0);
        self::assertSame('yes', $this->cache->get('eternal'));
    }

    public function testSetWithLongTtlIsReadable(): void
    {
        $this->cache->set('ttl', 'data', 3600);
        self::assertSame('data', $this->cache->get('ttl'));
    }

    public function testValueWithNewlines(): void
    {
        $value = "line1\nline2\nline3";
        $this->cache->set('multi', $value);
        self::assertSame($value, $this->cache->get('multi'));
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set('del', 'bye');
        $this->cache->delete('del');
        self::assertNull($this->cache->get('del'));
    }

    public function testDeleteNonExistingKeyDoesNotThrow(): void
    {
        $this->cache->delete('ghost');
        self::assertNull($this->cache->get('ghost'));
    }

    // ─── has ──────────────────────────────────────────────────────────────────

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('present', '1');
        self::assertTrue($this->cache->has('present'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::assertFalse($this->cache->has('absent'));
    }

    // ─── TTL ──────────────────────────────────────────────────────────────────

    public function testExpiredEntryReturnsNull(): void
    {
        // Write entry with 1-second TTL, then rewrite it with past timestamp via reflection
        $this->cache->set('exp', 'val', 100);

        // Manipulate file to have past expiry
        $files = glob($this->storageDir . '/*.cache') ?: [];
        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $newlinePos = (int) strpos($content, "\n");
            $value = substr($content, $newlinePos + 1);
            file_put_contents($file, (time() - 5) . "\n" . $value);
        }

        self::assertNull($this->cache->get('exp'));
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementInitializesNewKey(): void
    {
        $result = $this->cache->increment('counter');
        self::assertSame(1, $result);
        self::assertSame('1', $this->cache->get('counter'));
    }

    public function testIncrementAddsToExistingValue(): void
    {
        $this->cache->set('cnt', '10');
        $result = $this->cache->increment('cnt', 5);
        self::assertSame(15, $result);
        self::assertSame('15', $this->cache->get('cnt'));
    }

    public function testIncrementMultipleTimes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->cache->increment('multi');
        }
        self::assertSame('5', $this->cache->get('multi'));
    }

    // ─── key normalization ────────────────────────────────────────────────────

    public function testKeyWithSpecialCharactersIsNormalized(): void
    {
        $this->cache->set('user:42:profile', 'data');
        self::assertSame('data', $this->cache->get('user:42:profile'));
    }
}
