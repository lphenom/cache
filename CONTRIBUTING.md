# Contributing to lphenom/cache

Thank you for your interest in contributing! 🎉

## Requirements

- PHP >= 8.1
- Docker + Docker Compose (for running tests with services)
- Composer

## Development Setup

```bash
git clone git@github.com:lphenom/cache.git
cd cache

# Install dependencies (local packages must be checked out as siblings)
# Directory layout:
#   lphenom/
#     cache/    ← this repo
#     storage/  ← lphenom/storage
#     db/       ← lphenom/db
#     redis/    ← lphenom/redis

composer install

# Start MySQL + Redis
make up

# Run all tests
make test
```

## Code Style

PSR-12. Auto-fix:

```bash
make lint-fix
```

Check only:

```bash
make lint
```

## Static Analysis

```bash
make analyse   # PHPStan level 8
```

## KPHP Compatibility

All code **must** remain KPHP-compatible. Rules:

- No constructor property promotion (`__construct(private $x)`)
- No `readonly` properties
- No `Reflection`, `eval()`, `$$var`, `new $className()`
- No `str_starts_with`, `str_ends_with`, `str_contains` — use `substr`/`strpos`
- `try/catch` always with at least one explicit `catch`
- No `callable` stored in typed arrays

See [docs/kphp-compatibility.md](docs/kphp-compatibility.md) for full rules.

Verify:

```bash
make kphp-check
```

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(cache): add TTL support to FileCache
fix(cache): handle empty key after normalization
test(cache): add integration test for RedisCache
docs(cache): update driver usage examples
chore: bump phpunit to 10.6
```

## Pull Request Checklist

- [ ] Tests pass: `make test`
- [ ] No lint errors: `make lint`
- [ ] PHPStan passes: `make analyse`
- [ ] KPHP-compatible (no forbidden constructs)
- [ ] Docs updated if public API changed

## License

By contributing you agree that your changes will be licensed under the [MIT License](LICENSE).

