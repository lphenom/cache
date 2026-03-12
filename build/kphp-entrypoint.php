<?php

/**
 * KPHP entrypoint for lphenom/cache.
 *
 * Includes all source files in dependency order so KPHP can
 * type-check and compile the package to a static binary.
 *
 * File order: interfaces → exceptions → value objects → concrete classes.
 * All paths are relative to this file (build/).
 *
 * Dependencies are in vendor/ (populated by `composer install --no-dev`).
 *
 * @lphenom-build kphp
 */

declare(strict_types=1);

// ─── lphenom/storage ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/lphenom/storage/src/StorageException.php';
require_once __DIR__ . '/../vendor/lphenom/storage/src/StorageInterface.php';

// ─── lphenom/db ──────────────────────────────────────────────────────────────
// lphenom/db ^0.3 is natively KPHP-compatible: Param uses string $value
// (no int|string|bool|float|null union type), so no KPHP stubs are required.
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';

// ─── lphenom/redis ───────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/lphenom/redis/src/Pipeline/RedisPipelineDriverInterface.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Pipeline/RedisPipeline.php';
require_once __DIR__ . '/../vendor/lphenom/redis/src/Client/RedisClientInterface.php';

// ─── lphenom/cache ───────────────────────────────────────────────────────────
require_once __DIR__ . '/../src/Exception/CacheException.php';
require_once __DIR__ . '/../src/CacheInterface.php';
require_once __DIR__ . '/../src/KeyNormalizer.php';
require_once __DIR__ . '/../src/Driver/InMemoryCache.php';
require_once __DIR__ . '/../src/Driver/FileCache.php';
require_once __DIR__ . '/../src/Driver/DbCache.php';
require_once __DIR__ . '/../src/Driver/RedisCache.php';

// ─── Smoke test (KPHP binary must execute this block) ────────────────────────

$cache = new \LPhenom\Cache\Driver\InMemoryCache();
$cache->set('ping', 'pong', 0);
$val = $cache->get('ping');
if ($val !== 'pong') {
    echo 'ERROR: InMemoryCache get/set failed' . PHP_EOL;
    exit(1);
}

$cache->set('counter', '0', 0);
$result = $cache->increment('counter', 3, 0);
if ($result !== 3) {
    echo 'ERROR: InMemoryCache increment failed' . PHP_EOL;
    exit(1);
}

$cache->delete('ping');
if ($cache->has('ping')) {
    echo 'ERROR: InMemoryCache delete failed' . PHP_EOL;
    exit(1);
}

echo '=== KPHP entrypoint smoke-test: OK ===' . PHP_EOL;

