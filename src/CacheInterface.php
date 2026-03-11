<?php

/**
 * @lphenom-build shared,kphp
 *
 * CacheInterface — KPHP-compatible cache contract.
 *
 * No reflection, no callable types, no dynamic dispatch.
 * All implementations must throw CacheException on failure.
 */

declare(strict_types=1);

namespace LPhenom\Cache;

use LPhenom\Cache\Exception\CacheException;

/**
 * Cache contract for all LPhenom cache drivers.
 *
 * KPHP-compatible: no Reflection, no callable, no dynamic class loading.
 *
 * @lphenom-build shared,kphp
 */
interface CacheInterface
{
    /**
     * Get a cached value by key.
     *
     * Returns null if the key does not exist or has expired.
     *
     * @param string $key Cache key (will be normalized via KeyNormalizer).
     *
     * @throws CacheException On storage failure.
     */
    public function get(string $key): ?string;

    /**
     * Store a value in the cache.
     *
     * @param string $key        Cache key.
     * @param string $value      Value to cache.
     * @param int    $ttlSeconds TTL in seconds. 0 = no expiry (store forever).
     *
     * @throws CacheException On storage failure.
     */
    public function set(string $key, string $value, int $ttlSeconds = 0): void;

    /**
     * Delete a cached value.
     *
     * @param string $key Cache key.
     *
     * @throws CacheException On storage failure.
     */
    public function delete(string $key): void;

    /**
     * Check whether a key exists and has not expired.
     *
     * @param string $key Cache key.
     *
     * @throws CacheException On storage failure.
     */
    public function has(string $key): bool;

    /**
     * Atomically increment an integer value stored at key.
     *
     * If the key does not exist, it is initialized to 0 before incrementing.
     * Returns the new value after increment.
     *
     * @param string $key        Cache key.
     * @param int    $by         Amount to increment by (must be positive).
     * @param int    $ttlSeconds TTL in seconds. 0 = no expiry. Applied only on key creation.
     *
     * @throws CacheException On storage failure.
     */
    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int;
}
