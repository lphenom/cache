# =============================================================================
# Dockerfile — LPhenom Cache dev/test environment
#
# PHP 8.1-cli-alpine with pdo_mysql + composer
# All commands run inside this container via docker-compose.
#
# Usage:
#   docker-compose run --rm app composer install
#   docker-compose run --rm app vendor/bin/phpunit
# =============================================================================
FROM php:8.1-cli-alpine3.17

# Install system deps + PHP extensions
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-install pdo_mysql \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && apk add --no-cache git

# Install Composer (pinned version)
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Default: show PHP version
CMD ["php", "-v"]

