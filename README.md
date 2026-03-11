# lphenom/cache

[![CI](https://github.com/lphenom/cache/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/cache/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1--8.3-blue)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**LPhenom Cache** — KPHP-compatible caching library with file, in-memory, DB and Redis drivers.

Part of the [LPhenom](https://github.com/lphenom) ecosystem — a PHP framework that runs both as a classic PHP application and as a KPHP-compiled binary.

## Drivers

| Driver | Class | Backend |
|--------|-------|---------|
| File-based | `FileCache` | Filesystem via `lphenom/storage` |
| Database | `DbCache` | MySQL via `lphenom/db` |
| In-memory | `InMemoryCache` | PHP array (process lifetime) |
| Redis | `RedisCache` | Redis via `lphenom/redis` |

## Installation

```bash
composer require lphenom/cache
```

## Quick Start

```php
use LPhenom\Cache\Driver\InMemoryCache;
use LPhenom\Cache\Driver\FileCache;
use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Storage\LocalFilesystemStorage;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

// In-memory (testing / KPHP process)
$cache = new InMemoryCache();

// File-based
$cache = new FileCache(new LocalFilesystemStorage('/var/cache/app'));

// Redis (pure PHP RESP — KPHP-compatible)
$config = new RedisConnectionConfig('127.0.0.1', 6379);
$cache  = new RedisCache(RedisConnector::connectResp($config));

// Use any driver via the same interface
$cache->set('user:42', json_encode($user), 3600);
$data  = $cache->get('user:42');   // ?string
$exist = $cache->has('user:42');   // bool
$hits  = $cache->increment('page:hits', 1, 86400);
$cache->delete('user:42');
```

## Interface

```php
interface CacheInterface {
    public function get(string $key): ?string;
    public function set(string $key, string $value, int $ttlSeconds = 0): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int;
}
```

TTL `0` means no expiry. All keys are normalized via `KeyNormalizer` (forbidden chars replaced, max 64 bytes).

## Development

```bash
make up              # start MySQL + Redis via docker-compose
make install         # composer install inside the container
make test            # run all tests (unit + integration)
make test-unit       # unit tests only (no Docker services)
make lint            # PSR-12 check
make analyse         # PHPStan level 8
make kphp-check      # KPHP binary + PHAR build verification
make down            # stop services
```

## Documentation

- [Drivers](docs/drivers.md)
- [KPHP Compatibility](docs/kphp-compatibility.md)

## License

MIT — see [LICENSE](LICENSE).
