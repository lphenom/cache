<?php

/**
 * @lphenom-build shared,kphp
 *
 * InMemoryCache — in-process cache driver for testing and KPHP compiled mode.
 *
 * Stores all data in a PHP array. Data is lost when the process exits.
 * KPHP-compatible: no reflection, no callable, no dynamic dispatch.
 */

declare(strict_types=1);

namespace LPhenom\Cache\Driver;

use LPhenom\Cache\CacheInterface;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Cache\KeyNormalizer;

/**
 * In-memory cache driver.
 *
 * Stores all cached values in a plain PHP array.
 * Data is NOT persisted between requests / process restarts.
 *
 * Suitable for:
 * - Unit testing (no I/O required)
 * - KPHP compiled binaries (long-lived process, data in memory)
 *
 * @lphenom-build shared,kphp
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * Stored values indexed by normalized key.
     *
     * @var array<string, string>
     */
    private array $values = [];

    /**
     * Expiry timestamps indexed by normalized key.
     * 0 means no expiry.
     *
     * @var array<string, int>
     */
    private array $expires = [];

    public function get(string $key): ?string
    {
        $k = KeyNormalizer::normalize($key);

        if (!array_key_exists($k, $this->values)) {
            return null;
        }

        $exp = $this->expires[$k] ?? 0;
        if ($exp !== 0 && time() > $exp) {
            // Expired — clean up lazily
            unset($this->values[$k], $this->expires[$k]);
            return null;
        }

        return $this->values[$k];
    }

    public function set(string $key, string $value, int $ttlSeconds = 0): void
    {
        $k = KeyNormalizer::normalize($key);
        $this->values[$k]  = $value;
        $this->expires[$k] = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
    }

    public function delete(string $key): void
    {
        $k = KeyNormalizer::normalize($key);
        unset($this->values[$k], $this->expires[$k]);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int
    {
        $k = KeyNormalizer::normalize($key);

        $current = $this->get($key);

        if ($current === null) {
            // Key does not exist — initialize to 0 and apply TTL
            $newValue = $by;
            $this->values[$k]  = (string) $newValue;
            $this->expires[$k] = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
            return $newValue;
        }

        $newValue = (int) $current + $by;
        $this->values[$k] = (string) $newValue;
        // Do not change TTL on existing key

        return $newValue;
    }
}
