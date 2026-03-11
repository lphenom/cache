<?php

/**
 * @lphenom-build shared,kphp
 *
 * DbCache — database-backed cache driver using lphenom/db.
 *
 * KPHP-compatible: uses ConnectionInterface, no PDO directly.
 * In PHP mode: uses PdoMySqlConnection or FfiMySqlConnection.
 * In KPHP mode: uses FfiMySqlConnection.
 */

declare(strict_types=1);

namespace LPhenom\Cache\Driver;

use LPhenom\Cache\CacheInterface;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Cache\KeyNormalizer;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Param\ParamBinder;

/**
 * Database-backed cache driver.
 *
 * Requires a `cache` table:
 *   CREATE TABLE IF NOT EXISTS `cache` (
 *       `cache_key`  VARCHAR(64)  NOT NULL PRIMARY KEY,
 *       `cache_value` TEXT        NOT NULL,
 *       `expires_at`  INT         NOT NULL DEFAULT 0
 *   );
 *
 * TTL: expires_at = 0 means no expiry.
 * Lazy expiry: expired records are deleted on read.
 *
 * @lphenom-build shared,kphp
 */
final class DbCache implements CacheInterface
{
    /** @var \LPhenom\Db\Contract\ConnectionInterface */
    private ConnectionInterface $connection;

    public function __construct(
        ConnectionInterface $connection
    ) {
        $this->connection = $connection;
    }
    public function get(string $key): ?string
    {
        $k = KeyNormalizer::normalize($key);
        $exception = null;
        $result    = null;
        try {
            $result = $this->connection->query(
                'SELECT cache_value, expires_at FROM cache WHERE cache_key = :key',
                ['key' => ParamBinder::str($k)]
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('DbCache: failed to get key "' . $key . '".', 0, $exception);
        }
        if ($result === null) {
            return null;
        }
        $row = $result->fetchOne();
        if ($row === null) {
            return null;
        }
        $expiresAt = (int) ($row['expires_at'] ?? 0);
        if ($expiresAt !== 0 && time() > $expiresAt) {
            // Lazy delete expired entry
            $this->deleteByNormalizedKey($k);
            return null;
        }
        $value = $row['cache_value'] ?? null;
        if ($value === null) {
            return null;
        }
        return (string) $value;
    }
    public function set(string $key, string $value, int $ttlSeconds = 0): void
    {
        $k         = KeyNormalizer::normalize($key);
        $expiresAt = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $exception = null;
        try {
            $this->connection->execute(
                'REPLACE INTO cache (cache_key, cache_value, expires_at) VALUES (:key, :value, :expires)',
                [
                    'key'     => ParamBinder::str($k),
                    'value'   => ParamBinder::str($value),
                    'expires' => ParamBinder::int($expiresAt),
                ]
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('DbCache: failed to set key "' . $key . '".', 0, $exception);
        }
    }
    public function delete(string $key): void
    {
        $k = KeyNormalizer::normalize($key);
        $this->deleteByNormalizedKey($k);
    }
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int
    {
        $k = KeyNormalizer::normalize($key);
        // Try atomic SQL increment first
        $exception   = null;
        $affected    = 0;
        try {
            $affected = $this->connection->execute(
                'UPDATE cache SET cache_value = CAST(CAST(cache_value AS SIGNED) + :by AS CHAR)
                  WHERE cache_key = :key AND (expires_at = 0 OR expires_at > :now)',
                [
                    'by'  => ParamBinder::int($by),
                    'key' => ParamBinder::str($k),
                    'now' => ParamBinder::int(time()),
                ]
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('DbCache: failed to increment key "' . $key . '".', 0, $exception);
        }
        if ($affected > 0) {
            // Row was updated — fetch new value
            $current = $this->get($key);
            if ($current === null) {
                return $by;
            }
            return (int) $current;
        }
        // Row does not exist or was expired — insert new with value = $by
        $expiresAt = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $exception = null;
        try {
            $this->connection->execute(
                'REPLACE INTO cache (cache_key, cache_value, expires_at) VALUES (:key, :value, :expires)',
                [
                    'key'     => ParamBinder::str($k),
                    'value'   => ParamBinder::str((string) $by),
                    'expires' => ParamBinder::int($expiresAt),
                ]
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('DbCache: failed to insert during increment for key "' . $key . '".', 0, $exception);
        }
        return $by;
    }
    /**
     * Delete a cache entry by normalized key (internal helper).
     */
    private function deleteByNormalizedKey(string $normalizedKey): void
    {
        $exception = null;
        try {
            $this->connection->execute(
                'DELETE FROM cache WHERE cache_key = :key',
                ['key' => ParamBinder::str($normalizedKey)]
            );
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('DbCache: failed to delete key.', 0, $exception);
        }
    }
}
