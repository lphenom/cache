<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests\Integration;

use LPhenom\Cache\Driver\DbCache;
use LPhenom\Db\Driver\PdoMySqlConnection;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for DbCache using a real MySQL connection.
 *
 * Requires environment variables:
 *   DB_HOST  (default: 127.0.0.1)
 *   DB_PORT  (default: 3306)
 *   DB_NAME  (default: cache_test)
 *   DB_USER  (default: cache)
 *   DB_PASS  (default: secret)
 *
 * Run via docker-compose: make test-integration
 */
final class DbCacheIntegrationTest extends TestCase
{
    private DbCache $cache;
    private PdoMySqlConnection $connection;

    protected function setUp(): void
    {
        $host = (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1');
        $port = (string) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
        $name = (string) ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'cache_test');
        $user = (string) ($_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'cache');
        $pass = (string) ($_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'secret');

        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('pdo_mysql extension is not loaded.');
        }

        $exception = null;
        $conn      = null;

        try {
            $dsn  = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
            $conn = new PdoMySqlConnection($dsn, $user, $pass);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null || $conn === null) {
            self::markTestSkipped('Cannot connect to MySQL: ' . ($exception ? $exception->getMessage() : 'unknown'));
        }

        $this->connection = $conn;

        // Ensure cache table exists
        $this->connection->execute(
            'CREATE TABLE IF NOT EXISTS `cache` (
                `cache_key`   VARCHAR(64) NOT NULL PRIMARY KEY,
                `cache_value` TEXT        NOT NULL,
                `expires_at`  INT         NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            []
        );

        // Clean slate for each test
        $this->connection->execute('TRUNCATE TABLE `cache`', []);

        $this->cache = new DbCache($this->connection);
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

    // ─── TTL (lazy expiry) ────────────────────────────────────────────────────

    public function testExpiredEntryIsDeletedLazilyOnGet(): void
    {
        $past = time() - 10;
        $this->connection->execute(
            'REPLACE INTO cache (cache_key, cache_value, expires_at) VALUES (\'test_exp\', \'stale\', ' . $past . ')',
            []
        );

        self::assertNull($this->cache->get('test_exp'));

        // Also verify row was deleted
        self::assertFalse($this->cache->has('test_exp'));
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementInitializesNewKey(): void
    {
        $result = $this->cache->increment('counter');
        self::assertSame(1, $result);
    }

    public function testIncrementAddsToExistingValue(): void
    {
        $this->cache->set('cnt', '10');
        $result = $this->cache->increment('cnt', 5);
        self::assertSame(15, $result);
    }

    public function testIncrementByOne(): void
    {
        $this->cache->set('ctr', '99');
        $result = $this->cache->increment('ctr', 1);
        self::assertSame(100, $result);
    }

    public function testIncrementMultipleTimes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->cache->increment('multi');
        }
        $val = $this->cache->get('multi');
        self::assertSame('5', $val);
    }

    // ─── key normalization ────────────────────────────────────────────────────

    public function testKeyWithSpecialCharactersIsNormalized(): void
    {
        $this->cache->set('user:42:profile', 'data');
        self::assertSame('data', $this->cache->get('user:42:profile'));
    }
}
