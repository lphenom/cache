<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests;

use LPhenom\Cache\Driver\InMemoryCache;
use PHPUnit\Framework\TestCase;

final class InMemoryCacheTest extends TestCase
{
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
    }

    // ─── get / set ────────────────────────────────────────────────────────────

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->cache->get('missing'));
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('key', 'value');
        self::assertSame('value', $this->cache->get('key'));
    }

    public function testSetOverwritesExistingKey(): void
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

    public function testSetWithPositiveTtlKeepsValueWhileFresh(): void
    {
        $this->cache->set('fresh', 'data', 60);
        self::assertSame('data', $this->cache->get('fresh'));
    }

    public function testGetReturnsNullAfterTtlExpiry(): void
    {
        // Set ttl = 1 second in the past by manipulating the stored expiry directly.
        // We use a positive TTL first, then verify by setting ttl=-1 (already expired).
        // We can't mock time(), so we use ttl=1 and sleep — or we override via Reflection.
        // For speed, we use Reflection to set expires to past timestamp.
        $this->cache->set('expires', 'val', 100);

        $ref = new \ReflectionProperty(InMemoryCache::class, 'expires');
        $ref->setAccessible(true);
        /** @var array<string, int> $exp */
        $exp = $ref->getValue($this->cache);
        foreach ($exp as $k => $_) {
            $exp[$k] = time() - 1; // already expired
        }
        $ref->setValue($this->cache, $exp);

        self::assertNull($this->cache->get('expires'));
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

    public function testHasReturnsFalseAfterDelete(): void
    {
        $this->cache->set('temp', 'x');
        $this->cache->delete('temp');
        self::assertFalse($this->cache->has('temp'));
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementInitializesNewKey(): void
    {
        $result = $this->cache->increment('counter');
        self::assertSame(1, $result);
        self::assertSame('1', $this->cache->get('counter'));
    }

    public function testIncrementByOne(): void
    {
        $this->cache->set('cnt', '5');
        $result = $this->cache->increment('cnt');
        self::assertSame(6, $result);
        self::assertSame('6', $this->cache->get('cnt'));
    }

    public function testIncrementByCustomAmount(): void
    {
        $this->cache->set('cnt', '10');
        $result = $this->cache->increment('cnt', 5);
        self::assertSame(15, $result);
    }

    public function testIncrementNewKeyWithTtl(): void
    {
        $result = $this->cache->increment('new_cnt', 3, 60);
        self::assertSame(3, $result);
        self::assertSame('3', $this->cache->get('new_cnt'));
    }

    public function testIncrementDoesNotChangeTtlOnExistingKey(): void
    {
        // Set key with TTL, increment, TTL must still be set (not reset to 0)
        $this->cache->set('ctr', '0', 600);

        $ref = new \ReflectionProperty(InMemoryCache::class, 'expires');
        $ref->setAccessible(true);
        /** @var array<string, int> $beforeExp */
        $beforeExp = $ref->getValue($this->cache);

        $this->cache->increment('ctr', 1, 60);

        /** @var array<string, int> $afterExp */
        $afterExp = $ref->getValue($this->cache);

        // The expires entry should not have changed
        $normalizedKey = array_key_first($beforeExp);
        self::assertSame($beforeExp[$normalizedKey], $afterExp[$normalizedKey]);
    }

    public function testIncrementMultipleTimes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->cache->increment('multi');
        }
        self::assertSame('5', $this->cache->get('multi'));
    }

    // ─── key normalization ────────────────────────────────────────────────────

    public function testKeyWithSpecialCharsNormalized(): void
    {
        $this->cache->set('user:42:name', 'Alice');
        self::assertSame('Alice', $this->cache->get('user:42:name'));
        self::assertTrue($this->cache->has('user:42:name'));
    }
}
