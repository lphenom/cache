# Cache Drivers

## FileCache

Stores cache entries as plain text files via `lphenom/storage`.

```php
use LPhenom\Cache\Driver\FileCache;
use LPhenom\Storage\LocalFilesystemStorage;

$storage = new LocalFilesystemStorage('/var/cache/app');
$cache   = new FileCache($storage);
```

**File format:**
```
{expires_unix_timestamp}\n{value}
```
`0` in the first line means no expiry.

**Characteristics:**
- Works on shared hosting (no extensions required)
- Atomic writes (temp file + rename)
- Lazy expiry on `get()`

---

## DbCache

Stores cache entries in a MySQL table via `lphenom/db`.

### Table setup

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

**Characteristics:**
- Uses `REPLACE INTO` for atomic upsert
- Lazy expiry on `get()`
- `increment()` uses atomic SQL `UPDATE ... SET value = CAST(CAST(value AS SIGNED) + :by)`

---

## InMemoryCache

Stores values in a PHP array — data is lost when the process exits.

```php
use LPhenom\Cache\Driver\InMemoryCache;

$cache = new InMemoryCache();
```

**Use cases:**
- Unit testing (no I/O)
- KPHP compiled long-lived server processes
- Request-level caching

---

## RedisCache

Stores values in Redis via `lphenom/redis`.

### RespRedisClient (recommended, KPHP-compatible)

Uses pure PHP RESP protocol — no `ext-redis` required.

```php
use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

$config = new RedisConnectionConfig('127.0.0.1', 6379);
$client = RedisConnector::connectResp($config);
$cache  = new RedisCache($client);
```

### PhpRedisClient (PHP-only, requires ext-redis)

```php
$client = RedisConnector::connectPhpRedis($config);
$cache  = new RedisCache($client);
```

**TTL:**  passed directly to Redis `SET key value EX ttl`.

**`increment()`:**
- `by = 1` → atomic `INCR` command
- `by > 1` → `GET` + `SET` (not atomic under high concurrency)

---

## Key Normalization

All keys pass through `KeyNormalizer::normalize()`:

- Trims whitespace
- Replaces `{ } ( ) / \ @ : space .` with `_`
- Truncates to 64 bytes
- Throws `CacheException` if result is empty

```php
use LPhenom\Cache\KeyNormalizer;

KeyNormalizer::normalize('user:42:name');  // → 'user_42_name'
KeyNormalizer::normalize('  hello  ');     // → 'hello'
```

