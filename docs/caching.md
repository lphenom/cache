# LPhenom Cache

`lphenom/cache` — KPHP-compatible caching library for the LPhenom framework.

## Installation

```bash
composer require lphenom/cache
```

## Available Drivers

| Driver | Class | Requires |
|--------|-------|----------|
| File-based | `FileCache` | `lphenom/storage` |
| Database | `DbCache` | `lphenom/db` (MySQL) |
| In-memory | `InMemoryCache` | — |
| Redis | `RedisCache` | `lphenom/redis` |

## Quick Start

```php
use LPhenom\Cache\Driver\InMemoryCache;

$cache = new InMemoryCache();

$cache->set('user:42', json_encode(['name' => 'Alice']), 3600);

$data = $cache->get('user:42');  // string|null

if ($cache->has('user:42')) {
    $cache->delete('user:42');
}

$hits = $cache->increment('page:hits', 1, 86400);
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

> TTL `0` = store forever. All keys are normalized by `KeyNormalizer`.

## Documentation

- [Drivers](./drivers.md) — driver setup and configuration
- [KPHP Compatibility](./kphp-compatibility.md) — KPHP-specific notes

## Development

```bash
make up              # start MySQL + Redis
make test            # run all tests
make test-unit       # unit tests only (no services)
make test-integration # integration tests
make lint            # PSR-12 check
make analyse         # PHPStan level 8
make kphp-check      # KPHP binary + PHAR check
```

## License

MIT — see [LICENSE](../LICENSE).

