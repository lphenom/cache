<?php

/**
 * @lphenom-build shared,kphp
 *
 * RedisCache — Redis-backed cache driver using lphenom/redis.
 *
 * Works in both PHP mode (PhpRedisClient or RespRedisClient)
 * and KPHP compiled mode (RespRedisClient).
 *
 * RedisClientInterface does not have INCRBY, so increment-by-N
 * is implemented as get/set fallback (not atomic for N>1).
 * For increment-by-1 the atomic INCR command is used.
 */

declare(strict_types=1);

namespace LPhenom\Cache\Driver;

use LPhenom\Cache\CacheInterface;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Cache\KeyNormalizer;
use LPhenom\Redis\Client\RedisClientInterface;

/**
 * Redis-backed cache driver.
 *
 * Uses lphenom/redis RedisClientInterface — KPHP-compatible in both modes.
 *
 * In PHP mode:  pass PhpRedisClient or RespRedisClient.
 * In KPHP mode: pass RespRedisClient (pure PHP RESP — no ext-redis required).
 *
 * Note on increment: RedisClientInterface::incr() increments by 1 (atomic).
 * For increment-by-N > 1 a get/set fallback is used (not atomic under concurrency).
 *
 * @lphenom-build shared,kphp
 */
final class RedisCache implements CacheInterface
{
    /** @var RedisClientInterface */
    private RedisClientInterface $redis;

    public function __construct(RedisClientInterface $redis)
    {
        $this->redis = $redis;
    }

    public function get(string $key): ?string
    {
        $k         = KeyNormalizer::normalize($key);
        $exception = null;
        $value     = null;

        try {
            $value = $this->redis->get($k);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new CacheException('RedisCache: failed to get key "' . $key . '".', 0);
        }

        return $value;
    }

    public function set(string $key, string $value, int $ttlSeconds = 0): void
    {
        $k         = KeyNormalizer::normalize($key);
        $exception = null;

        try {
            $this->redis->set($k, $value, $ttlSeconds);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new CacheException('RedisCache: failed to set key "' . $key . '".', 0);
        }
    }

    public function delete(string $key): void
    {
        $k         = KeyNormalizer::normalize($key);
        $exception = null;

        try {
            $this->redis->del($k);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new CacheException('RedisCache: failed to delete key "' . $key . '".', 0);
        }
    }

    public function has(string $key): bool
    {
        $k         = KeyNormalizer::normalize($key);
        $exception = null;
        $exists    = false;

        try {
            $exists = $this->redis->exists($k);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new CacheException('RedisCache: failed to check key "' . $key . '".', 0);
        }

        return $exists;
    }

    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int
    {
        $k = KeyNormalizer::normalize($key);

        // For $by === 1 use atomic INCR.
        // For $by > 1 use get/set pattern (not atomic, suitable for low-concurrency).
        if ($by === 1) {
            $exception = null;
            $newValue  = 0;

            try {
                $newValue = $this->redis->incr($k);
                if ($ttlSeconds > 0 && $newValue === 1) {
                    // Key was just created — set TTL
                    $this->redis->expire($k, $ttlSeconds);
                }
            } catch (\Throwable $e) {
                $exception = $e;
            }

            if ($exception !== null) {
                throw new CacheException('RedisCache: failed to increment key "' . $key . '".', 0);
            }

            return $newValue;
        }

        // $by > 1 — get current, add $by, set back
        $current = $this->get($key);

        if ($current === null) {
            $newValue  = $by;
            $exception = null;

            try {
                $this->redis->set($k, (string) $newValue, $ttlSeconds);
            } catch (\Throwable $e) {
                $exception = $e;
            }

            if ($exception !== null) {
                throw new CacheException('RedisCache: failed to increment key "' . $key . '".', 0);
            }

            return $newValue;
        }

        $newValue  = (int) $current + $by;
        $exception = null;

        try {
            // SET with ttl=0 preserves Redis TTL behaviour (key stays until explicit delete or server TTL)
            $this->redis->set($k, (string) $newValue, 0);
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($exception !== null) {
            throw new CacheException('RedisCache: failed to increment key "' . $key . '".', 0);
        }

        return $newValue;
    }
}
