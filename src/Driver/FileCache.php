<?php

/**
 * @lphenom-build shared,kphp
 *
 * FileCache - filesystem cache driver using lphenom/storage.
 */

declare(strict_types=1);

namespace LPhenom\Cache\Driver;

use LPhenom\Cache\CacheInterface;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Cache\KeyNormalizer;
use LPhenom\Storage\StorageInterface;

/**
 * File-based cache driver.
 *
 * File format (plain text, no serialization):
 *   Line 1: expiry Unix timestamp (0 = no expiry)
 *   Line 2+: the cached value
 *
 * @lphenom-build shared,kphp
 */
final class FileCache implements CacheInterface
{
    private const FILE_EXTENSION = '.cache';

    /** @var \LPhenom\Storage\StorageInterface */
    private StorageInterface $storage;

    public function __construct(
        StorageInterface $storage
    ) {
        $this->storage = $storage;
    }
    public function get(string $key): ?string
    {
        $path = $this->keyToPath($key);
        if (!$this->storage->exists($path)) {
            return null;
        }
        $exception = null;
        $raw       = null;
        try {
            $raw = $this->storage->get($path);
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null || $raw === null) {
            return null;
        }
        return $this->parseEntry((string) $raw);
    }
    public function set(string $key, string $value, int $ttlSeconds = 0): void
    {
        $path    = $this->keyToPath($key);
        $expires = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $content = $expires . "\n" . $value;
        $exception = null;
        try {
            $this->storage->put($path, $content);
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('FileCache: failed to write key "' . $key . '".', 0);
        }
    }
    public function delete(string $key): void
    {
        $path = $this->keyToPath($key);
        if (!$this->storage->exists($path)) {
            return;
        }
        $exception = null;
        try {
            $this->storage->delete($path);
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('FileCache: failed to delete key "' . $key . '".', 0);
        }
    }
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int
    {
        $path = $this->keyToPath($key);
        $currentRaw   = null;
        $currentValue = 0;
        $currentExp   = 0;
        if ($this->storage->exists($path)) {
            $exception = null;
            $raw       = null;
            try {
                $raw = $this->storage->get($path);
            } catch (\Throwable $e) {
                $exception = $e;
            }
            if ($exception === null && $raw !== null) {
                $currentRaw = (string) $raw;
                $newlinePos = strpos($currentRaw, "\n");
                if ($newlinePos !== false) {
                    $expLine     = substr($currentRaw, 0, $newlinePos);
                    $storedValue = substr($currentRaw, $newlinePos + 1);
                    $currentExp  = (int) $expLine;
                    if ($currentExp !== 0 && time() > $currentExp) {
                        $currentValue = 0;
                        $currentExp   = 0;
                    } else {
                        $currentValue = (int) $storedValue;
                    }
                }
            }
        }
        $newValue = $currentValue + $by;
        $expires  = ($currentRaw === null)
            ? ($ttlSeconds > 0 ? time() + $ttlSeconds : 0)
            : $currentExp;
        $content   = $expires . "\n" . $newValue;
        $exception = null;
        try {
            $this->storage->put($path, $content);
        } catch (\Throwable $e) {
            $exception = $e;
        }
        if ($exception !== null) {
            throw new CacheException('FileCache: failed to increment key "' . $key . '".', 0);
        }
        return $newValue;
    }
    private function parseEntry(string $raw): ?string
    {
        $newlinePos = strpos($raw, "\n");
        if ($newlinePos === false) {
            return null;
        }
        $expLine = substr($raw, 0, $newlinePos);
        $value   = substr($raw, $newlinePos + 1);
        $expires = (int) $expLine;
        if ($expires !== 0 && time() > $expires) {
            return null;
        }
        return $value;
    }
    private function keyToPath(string $key): string
    {
        return KeyNormalizer::normalize($key) . self::FILE_EXTENSION;
    }
}
