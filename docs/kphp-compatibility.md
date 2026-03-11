# KPHP Compatibility — lphenom/cache

All cache drivers are designed for KPHP-compiled mode.

## Compliant patterns used

| Pattern | Status |
|---------|--------|
| `declare(strict_types=1)` in every file | ✅ |
| No constructor property promotion | ✅ |
| No `readonly` properties | ✅ |
| No `Reflection` API | ✅ |
| No `eval()` or `$$var` | ✅ |
| No `callable` in arrays | ✅ |
| `try/catch` with explicit `catch (\Throwable $e)` | ✅ |
| `strpos()` / `substr()` instead of `str_contains` etc. | ✅ |
| `array<K, V>` PHPDoc on all arrays | ✅ |
| No `match` expressions | ✅ |
| Explicit `new ClassName()` (not dynamic) | ✅ |

## KPHP-incompatible fixes applied

### Constructor property promotion removed

```php
// ❌ Old (not KPHP compatible)
public function __construct(private StorageInterface $storage) {}

// ✅ Fixed
private StorageInterface $storage;
public function __construct(StorageInterface $storage) {
    $this->storage = $storage;
}
```

Same fix applied to `DbCache` and `RedisCache`.

## KPHP entrypoint

For KPHP compilation see `build/kphp-entrypoint.php`.

Run `make kphp-check` to verify KPHP compilation:

```bash
make kphp-check
```

This:
1. Copies dependency sources into `build/vendor-src/`
2. Runs `docker build -f Dockerfile.check` (two stages: KPHP binary + PHAR)
3. Executes the compiled binary to verify it runs correctly

## Driver availability in each mode

| Driver | PHP shared hosting | KPHP compiled |
|--------|-------------------|---------------|
| `InMemoryCache` | ✅ | ✅ |
| `FileCache` | ✅ | ✅ (via lphenom/storage) |
| `DbCache` | ✅ (PDO) | ✅ (via lphenom/db FfiMySql) |
| `RedisCache` + `RespRedisClient` | ✅ (pure PHP) | ✅ |
| `RedisCache` + `PhpRedisClient` | ✅ (ext-redis) | ❌ |

## References

- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [vkcom/kphp Docker image](https://hub.docker.com/r/vkcom/kphp)

