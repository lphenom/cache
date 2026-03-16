# Драйверы кэша

## FileCache

Хранит записи кэша в виде текстовых файлов через `lphenom/storage`.

**Требует:** `lphenom/storage ^0.1`

```bash
composer require lphenom/storage:^0.1
```

```php
use LPhenom\Cache\Driver\FileCache;
use LPhenom\Storage\LocalFilesystemStorage;

$storage = new LocalFilesystemStorage('/var/cache/app');
$cache   = new FileCache($storage);
```

**Формат файла:**
```
{unix_timestamp_истечения}\n{значение}
```
`0` в первой строке означает отсутствие срока жизни.

**Характеристики:**
- Работает на shared hosting (расширения не требуются)
- Атомарная запись (временный файл + rename)
- Ленивое истечение при `get()`

---

## DbCache

Хранит записи кэша в таблице MySQL через `lphenom/db`.

**Требует:** `lphenom/db ^0.1`

```bash
composer require lphenom/db:^0.1
```

### Создание таблицы

```sql
CREATE TABLE IF NOT EXISTS `cache` (
    `cache_key`   VARCHAR(64) NOT NULL PRIMARY KEY,
    `cache_value` TEXT        NOT NULL,
    `expires_at`  INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
use LPhenom\Cache\Driver\DbCache;
use LPhenom\Db\Driver\PdoMySqlConnection;

$dsn        = 'mysql:host=127.0.0.1;port=3306;dbname=mydb;charset=utf8mb4';
$connection = new PdoMySqlConnection($dsn, 'user', 'pass');
$cache      = new DbCache($connection);
```

**Характеристики:**
- Использует `REPLACE INTO` для атомарного upsert
- Ленивое истечение при `get()`
- `increment()` использует атомарный SQL: `UPDATE ... SET value = CAST(CAST(value AS SIGNED) + :by)`

---

## InMemoryCache

Хранит значения в PHP-массиве — данные теряются при завершении процесса.

```php
use LPhenom\Cache\Driver\InMemoryCache;

$cache = new InMemoryCache();
```

**Варианты использования:**
- Юнит-тестирование (без I/O)
- Долгоживущие KPHP-серверные процессы
- Кэширование на уровне запроса

---

## RedisCache

Хранит значения в Redis через `lphenom/redis`.

**Требует:** `lphenom/redis ^0.1`

```bash
composer require lphenom/redis:^0.1
```

### RespRedisClient (рекомендуется, совместим с KPHP)

Использует чистый PHP RESP-протокол — `ext-redis` не требуется.

```php
use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

$config = new RedisConnectionConfig('127.0.0.1', 6379);
$client = RedisConnector::connectResp($config);
$cache  = new RedisCache($client);
```

### PhpRedisClient (только PHP, требует ext-redis)

```php
$client = RedisConnector::connectPhpRedis($config);
$cache  = new RedisCache($client);
```

**TTL:** передаётся напрямую в Redis-команду `SET key value EX ttl`.

**`increment()`:**
- `by = 1` → атомарная команда `INCR`
- `by > 1` → `GET` + `SET` (не атомарно при высокой конкурентности)

---

## Нормализация ключей

Все ключи проходят через `KeyNormalizer::normalize()`:

- Обрезает пробелы
- Заменяет `{ } ( ) / \ @ : пробел .` на `_`
- Усекает до 64 байт
- Бросает `CacheException` если результат пустой

```php
use LPhenom\Cache\KeyNormalizer;

KeyNormalizer::normalize('user:42:name');  // → 'user_42_name'
KeyNormalizer::normalize('  hello  ');     // → 'hello'
```
