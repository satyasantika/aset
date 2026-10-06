#!/bin/sh
# Entrypoint SIMAN. Peran `app` (php-fpm) menyiapkan basis data dan cache; peran lain langsung menjalankan perintahnya.
set -e
cd /var/www/html

if [ "$1" = "php-fpm" ]; then
    DB_FILE="${DB_DATABASE:-/data/database.sqlite}"
    mkdir -p "$(dirname "$DB_FILE")"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
    chown -R www-data:www-data "$(dirname "$DB_FILE")" storage bootstrap/cache

    php artisan migrate --force
    php artisan optimize
    php artisan filament:optimize
    php artisan icons:cache 2>/dev/null || true
fi

exec "$@"
