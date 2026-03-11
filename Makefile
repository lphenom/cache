.PHONY: up down build test test-unit test-integration lint lint-fix analyse \
        install kphp-check prepare-vendor-src phar-check

# ─── Docker Compose ───────────────────────────────────────────────────────────

## Start all services (MySQL + Redis + app)
up:
	docker-compose up -d

## Stop all services
down:
	docker-compose down

## (Re)build the app Docker image
build:
	docker-compose build app

## Open a shell inside the app container
shell:
	docker-compose run --rm app sh

# ─── Composer ─────────────────────────────────────────────────────────────────

## Install dependencies inside the app container
install:
	docker-compose run --rm app composer install --no-interaction

# ─── Tests ────────────────────────────────────────────────────────────────────

## Run unit tests only (no Docker services needed)
test-unit:
	docker-compose run --rm --no-deps app \
		vendor/bin/phpunit --testsuite=Unit

## Run unit + integration tests (requires MySQL + Redis to be up)
test-integration: up
	docker-compose run --rm app \
		vendor/bin/phpunit --testsuite=Integration

## Run all tests (unit + integration)
test: up
	docker-compose run --rm app \
		vendor/bin/phpunit

## Alias for test
check: test

# ─── Code quality ─────────────────────────────────────────────────────────────

## Run PHP_CodeSniffer (PSR-12)
lint:
	docker-compose run --rm --no-deps app \
		vendor/bin/phpcs --standard=PSR12 src/ tests/

## Auto-fix coding standard issues
lint-fix:
	docker-compose run --rm --no-deps app \
		vendor/bin/phpcbf --standard=PSR12 src/ tests/

## Run PHPStan static analysis (level 8)
analyse:
	docker-compose run --rm --no-deps app \
		vendor/bin/phpstan analyse src/ --level=8

# ─── KPHP + PHAR verification ─────────────────────────────────────────────────

## Copy local dependency sources into build/vendor-src/ for Dockerfile.check
prepare-vendor-src:
	@echo "==> Preparing build/vendor-src/ from sibling packages..."
	@rm -rf build/vendor-src
	@mkdir -p build/vendor-src/storage build/vendor-src/db build/vendor-src/redis
	@cp -r ../storage/src/.  build/vendor-src/storage/
	@cp -r ../db/src/.       build/vendor-src/db/
	@cp -r ../redis/src/.    build/vendor-src/redis/
	@echo "==> Done."

## Build KPHP binary + PHAR (runs Dockerfile.check, uses VCS repos from GitHub)
kphp-check:
	@echo "==> Running KPHP + PHAR check..."
	docker build -f Dockerfile.check --target check-complete -t lphenom-cache-check .
	@echo "==> KPHP + PHAR check passed."

## (Legacy) Copy local dependency sources into build/vendor-src/ for offline KPHP check
prepare-vendor-src:
	docker-compose run --rm --no-deps app \
		php -d phar.readonly=0 build/build-phar.php
	docker-compose run --rm --no-deps app \
		php build/smoke-test-phar.php lphenom-cache.phar

