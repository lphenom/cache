<?php

/**
 * @lphenom-build shared,kphp
 *
 * KeyNormalizer — produces safe, consistent cache keys.
 *
 * KPHP-compatible: uses substr/strpos instead of str_starts_with/str_contains.
 * No reflection, no eval, no dynamic dispatch.
 */

declare(strict_types=1);

namespace LPhenom\Cache;

use LPhenom\Cache\Exception\CacheException;

/**
 * Normalizes cache keys to be safe for all cache drivers.
 *
 * Rules applied:
 * - Trims leading/trailing whitespace
 * - Replaces characters forbidden by PSR-6 ({, }, (, ), /, \, @, :) and space with underscore
 * - Replaces dots with underscore for filesystem safety
 * - Truncates to 64 bytes
 * - Throws CacheException if the result is an empty string
 *
 * @lphenom-build shared,kphp
 */
final class KeyNormalizer
{
    private const MAX_KEY_LENGTH = 64;

    /**
     * Normalize a cache key.
     *
     * @param string $key Raw cache key.
     *
     * @return string Safe, normalized key (max 64 chars).
     *
     * @throws CacheException If the resulting key is empty.
     */
    public static function normalize(string $key): string
    {
        // Trim whitespace
        $key = trim($key);

        if ($key === '') {
            throw new CacheException('Cache key must not be empty.');
        }

        // Replace forbidden/unsafe characters with underscore
        // Forbidden: { } ( ) / \ @ : space .
        $key = str_replace(
            ['{', '}', '(', ')', '/', '\\', '@', ':', ' ', '.'],
            '_',
            $key
        );

        // Truncate to max length (bytes)
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            $key = substr($key, 0, self::MAX_KEY_LENGTH);
        }

        // After all transformations, key could theoretically be empty if it was only forbidden chars
        if ($key === '') {
            throw new CacheException('Cache key must not be empty after normalization.');
        }

        return $key;
    }
}
