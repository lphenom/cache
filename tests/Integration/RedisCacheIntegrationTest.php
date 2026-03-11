<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests\Integration;

use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for RedisCache using a real Redis connection via RespRedisClient.
 *
 * Uses pure PHP RESP protocol — no ext-redis extension required.
 *
 * Requires environment variables:
 *   REDIS_HOST  (default: 127.0.0.1)
 *   REDIS_PORT  (default: 6379)
 *
 * Run via docker-compose: make test-integration
 */
final class RedisCacheIntegrationTest extends TestCase
{
    private RedisCache $cache;

    /** @var string Unique key prefix to isolate this test run */
    private string $prefix;

    protected function setUp(): void
    {
        $host = (string) ($_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: '127.0.0.1');
        $port = (int)    ($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: '6379');

        $exception = null;
        $client    = null;

        try {
            $config = new RedisConnectionConfig($host, $port);
            $client = RedisConnector::connectResp($config);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null || $client === null) {
            self::markTestSkipped('Cannot connect to Redis: ' . ($exception ? $exception->getMessage() : 'unknown'));
        }

        $this->cache  = new RedisCache($client);
        $this->prefix = 'integration_test_' . getmypid() . '_' . microtime(true) . '_';
    }

    protected function tearDown(): void
    {
        // Keys are namespaced by prefix — they'll expire or be cleaned by Redis
    }

    /** Prefixed key so tests don't collide with other data. */
    private function k(string $key): string
    {
        return $this->prefix . $key;
    }

    // ─── get / set ────────────────────────────────────────────────────────────

    public function testSetAndGet(): void
    {
        $this->cache->set($this->k('hello'), 'world');
        self::assertSame('world', $this->cache->get($this->k('hello')));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->cache->get($this->k('nonexistent')));
    }

    public function testOverwriteValue(): void
    {
        $this->cache->set($this->k('key'), 'first');
        $this->cache->set($this->k('key'), 'second');
        self::assertSame('second', $this->cache->get($this->k('key')));
    }

    public function testSetWithPositiveTtlIsReadable(): void
    {
        $this->cache->set($this->k('ttl'), 'data', 60);
        self::assertSame('data', $this->cache->get($this->k('ttl')));
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set($this->k('del'), 'bye');
        $this->cache->delete($this->k('del'));
        self::assertNull($this->cache->get($this->k('del')));
    }

    public function testDeleteNonExistingKeyDoesNotThrow(): void
    {
        $this->cache->delete($this->k('ghost'));
        self::assertNull($this->cache->get($this->k('ghost')));
    }

    // ─── has ──────────────────────────────────────────────────────────────────

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set($this->k('present'), '1');
        self::assertTrue($this->cache->has($this->k('present')));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::assertFalse($this->cache->has($this->k('absent')));
    }

    // ─── TTL ──────────────────────────────────────────────────────────────────

    public function testKeyWithTtlExpiresEventually(): void
    {
        $this->cache->set($this->k('exp'), 'val', 1);
        self::assertSame('val', $this->cache->get($this->k('exp')));

        sleep(2);

        self::assertNull($this->cache->get($this->k('exp')));
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementInitializesNewKey(): void
    {
        $result = $this->cache->increment($this->k('counter'));
        self::assertSame(1, $result);
    }

    public function testIncrementByOne(): void
    {
        $this->cache->set($this->k('ctr'), '9');
        // For increment-by-N > 1 we use get/set, set replaces Redis TTL.
        // For atomic +1, use incr directly.
        $result = $this->cache->increment($this->k('ctr'), 1);
        self::assertSame(10, $result);
    }

    public function testIncrementByCustomAmount(): void
    {
        $this->cache->set($this->k('cnt'), '10');
        $result = $this->cache->increment($this->k('cnt'), 5);
        self::assertSame(15, $result);
    }

    public function testIncrementMultipleTimes(): void
    {
        $key = $this->k('multi');
        for ($i = 0; $i < 5; $i++) {
            $this->cache->increment($key, 1);
        }
        self::assertSame(5, (int) $this->cache->get($key));
    }

    public function testIncrementNewKeyWithTtlSetsExpiry(): void
    {
        $key = $this->k('newcnt');
        $this->cache->increment($key, 1, 60);

        // Key should exist immediately after
        self::assertTrue($this->cache->has($key));
    }

    // ─── key normalization ────────────────────────────────────────────────────

    public function testKeyWithSpecialCharactersIsNormalized(): void
    {
        $rawKey = $this->k('user:42:profile');
        $this->cache->set($rawKey, 'data');
        self::assertSame('data', $this->cache->get($rawKey));
    }
}
