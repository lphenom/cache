# LPhenom Cache

`lphenom/cache` — KPHP-совместимая библиотека кэширования для фреймворка LPhenom.

## Установка

```bash
composer require lphenom/cache
```

## Доступные драйверы

| Драйвер      | Класс           | Требует                  |
|--------------|-----------------|--------------------------|
| Файловый     | `FileCache`     | `lphenom/storage`        |
| База данных  | `DbCache`       | `lphenom/db` (MySQL)     |
| В памяти     | `InMemoryCache` | —                        |
| Redis        | `RedisCache`    | `lphenom/redis`          |

## Быстрый старт

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

> TTL `0` = хранить вечно. Все ключи нормализуются через `KeyNormalizer`.

## Документация

- [Драйверы](./drivers.md) — настройка и конфигурация драйверов
- [Совместимость с KPHP](./kphp-compatibility.md) — особенности KPHP

## Разработка

```bash
make up              # запустить MySQL + Redis
make test            # запустить все тесты
make test-unit       # только юнит-тесты (без сервисов)
make test-integration # интеграционные тесты
make lint            # проверка PSR-12
make analyse         # PHPStan уровень 8
make kphp-check      # проверка KPHP binary + PHAR
```

## Лицензия

MIT — см. [LICENSE](../LICENSE).
