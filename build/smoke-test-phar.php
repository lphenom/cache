#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test for lphenom/cache.
 *
 * Loads the built PHAR and verifies autoloading + basic functionality.
 *
 * Usage: php build/smoke-test-phar.php /path/to/lphenom-cache.phar
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/lphenom-cache.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, 'PHAR not found: ' . $pharFile . PHP_EOL);
    exit(1);
}

require $pharFile;

// ── InMemoryCache ─────────────────────────────────────────────────────────────
$cache = new \LPhenom\Cache\Driver\InMemoryCache();

$cache->set('foo', 'bar');
$val = $cache->get('foo');
if ($val !== 'bar') {
    fwrite(STDERR, 'FAIL: InMemoryCache get/set returned ' . var_export($val, true) . PHP_EOL);
    exit(1);
}
echo 'smoke-test: InMemoryCache set/get ok' . PHP_EOL;

$cache->delete('foo');
if ($cache->has('foo')) {
    fwrite(STDERR, 'FAIL: InMemoryCache delete/has failed' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: InMemoryCache delete/has ok' . PHP_EOL;

$n = $cache->increment('counter', 1, 0);
if ($n !== 1) {
    fwrite(STDERR, 'FAIL: InMemoryCache increment returned ' . $n . PHP_EOL);
    exit(1);
}
echo 'smoke-test: InMemoryCache increment ok' . PHP_EOL;

// ── KeyNormalizer ─────────────────────────────────────────────────────────────
$key = \LPhenom\Cache\KeyNormalizer::normalize('user:42:name');
if ($key !== 'user_42_name') {
    fwrite(STDERR, 'FAIL: KeyNormalizer returned ' . $key . PHP_EOL);
    exit(1);
}
echo 'smoke-test: KeyNormalizer ok' . PHP_EOL;

// ── CacheInterface implemented ────────────────────────────────────────────────
if (!($cache instanceof \LPhenom\Cache\CacheInterface)) {
    fwrite(STDERR, 'FAIL: InMemoryCache does not implement CacheInterface' . PHP_EOL);
    exit(1);
}
echo 'smoke-test: CacheInterface ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

