# lphenom/cache

[![CI](https://github.com/lphenom/cache/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/cache/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1--8.3-blue)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**LPhenom Cache** — KPHP-совместимая библиотека кэширования с файловым, in-memory, DB и Redis драйверами.

Часть экосистемы [LPhenom](https://github.com/lphenom) — PHP-фреймворка, работающего как в классическом PHP-режиме, так и в виде KPHP-скомпилированного бинарника.

## Драйверы

| Драйвер      | Класс           | Бэкенд                               |
|--------------|-----------------|--------------------------------------|
| Файловый     | `FileCache`     | Файловая система через `lphenom/storage` |
| База данных  | `DbCache`       | MySQL через `lphenom/db`             |
| В памяти     | `InMemoryCache` | PHP-массив (время жизни процесса)    |
| Redis        | `RedisCache`    | Redis через `lphenom/redis`          |

## Установка

```bash
composer require lphenom/cache
```

## Быстрый старт

```php
use LPhenom\Cache\Driver\InMemoryCache;
use LPhenom\Cache\Driver\FileCache;
use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Storage\LocalFilesystemStorage;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

// In-memory (тестирование / KPHP-процесс)
$cache = new InMemoryCache();

// Файловый
$cache = new FileCache(new LocalFilesystemStorage('/var/cache/app'));

// Redis (чистый PHP RESP — совместим с KPHP)
$config = new RedisConnectionConfig('127.0.0.1', 6379);
$cache  = new RedisCache(RedisConnector::connectResp($config));

// Любой драйвер через единый интерфейс
$cache->set('user:42', json_encode($user), 3600);
$data  = $cache->get('user:42');   // ?string
$exist = $cache->has('user:42');   // bool
$hits  = $cache->increment('page:hits', 1, 86400);
$cache->delete('user:42');
```

## Интерфейс

```php
interface CacheInterface {
    public function get(string $key): ?string;
    public function set(string $key, string $value, int $ttlSeconds = 0): void;
    public function delete(string $key): void;
    public function has(string $key): bool;
    public function increment(string $key, int $by = 1, int $ttlSeconds = 0): int;
}
```

TTL `0` означает бессрочное хранение. Все ключи нормализуются через `KeyNormalizer` (запрещённые символы заменяются, максимум 64 байта).

## Разработка

```bash
make up              # запустить MySQL + Redis через docker-compose
make install         # composer install внутри контейнера
make test            # запустить все тесты (unit + integration)
make test-unit       # только юнит-тесты (без Docker-сервисов)
make lint            # проверка PSR-12
make analyse         # PHPStan уровень 8
make kphp-check      # проверка KPHP binary + PHAR
make down            # остановить сервисы
```

## Документация

- [Драйверы](docs/drivers.md)
- [Совместимость с KPHP](docs/kphp-compatibility.md)

## Лицензия

MIT — см. [LICENSE](LICENSE).
