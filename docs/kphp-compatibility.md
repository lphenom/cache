# Совместимость с KPHP — lphenom/cache

Все драйверы кэша разработаны для работы в режиме KPHP-компиляции.

## Применяемые паттерны совместимости

| Паттерн | Статус |
|---------|--------|
| `declare(strict_types=1)` во всех файлах | ✅ |
| Без constructor property promotion | ✅ |
| Без `readonly` свойств | ✅ |
| Без `Reflection` API | ✅ |
| Без `eval()` и `$$var` | ✅ |
| Без `callable` в массивах | ✅ |
| `try/catch` с явным `catch (\Throwable $e)` | ✅ |
| `strpos()` / `substr()` вместо `str_contains` и т.д. | ✅ |
| `array<K, V>` PHPDoc на всех массивах | ✅ |
| Без выражений `match` | ✅ |
| Явный `new ClassName()` (не динамический) | ✅ |

## Исправленные KPHP-несовместимости

### Удалён constructor property promotion

```php
// ❌ Старый вариант (не совместим с KPHP)
public function __construct(private StorageInterface $storage) {}

// ✅ Исправлено
private StorageInterface $storage;
public function __construct(StorageInterface $storage) {
    $this->storage = $storage;
}
```

Аналогичное исправление применено к `DbCache` и `RedisCache`.

## Точка входа KPHP

Для KPHP-компиляции используется `build/kphp-entrypoint.php`.

Запустите `make kphp-check` для проверки KPHP-компиляции:

```bash
make kphp-check
```

Это:
1. Копирует исходники зависимостей в `build/vendor-src/`
2. Запускает `docker build -f Dockerfile.check` (два этапа: KPHP binary + PHAR)
3. Выполняет скомпилированный бинарник для проверки корректности

## Доступность драйверов в каждом режиме

| Драйвер | PHP shared hosting | KPHP compiled |
|---------|--------------------|---------------|
| `InMemoryCache` | ✅ | ✅ |
| `FileCache` | ✅ | ✅ (через lphenom/storage) |
| `DbCache` | ✅ (PDO) | ✅ (через lphenom/db FfiMySql) |
| `RedisCache` + `RespRedisClient` | ✅ (чистый PHP) | ✅ |
| `RedisCache` + `PhpRedisClient` | ✅ (ext-redis) | ❌ |

## Ссылки

- [Различия KPHP vs PHP](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [Docker-образ vkcom/kphp](https://hub.docker.com/r/vkcom/kphp)
