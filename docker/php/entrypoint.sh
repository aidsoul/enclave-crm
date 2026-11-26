#!/bin/sh
set -e

umask 0002

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public/delivery-proofs \
    storage/app/public/delivery-signatures \
    storage/app/public/chat \
    bootstrap/cache \
    database

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
find storage bootstrap/cache -type d -exec chmod 2775 {} + 2>/dev/null || true
find storage bootstrap/cache -type f -exec chmod 664 {} + 2>/dev/null || true

if [ ! -L public/storage ] || [ ! -e public/storage ]; then
    rm -rf public/storage
    php artisan storage:link --quiet 2>/dev/null || true
fi

exec "$@"
