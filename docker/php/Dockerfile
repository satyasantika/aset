# Runtime PHP 8.4 untuk menjalankan composer/artisan/test tanpa Sail.
# Bangun: docker build -t siman-php:8.4 docker/php
FROM php:8.4-cli
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl gd redis bcmath pcntl posix zip pdo_sqlite exif
