#!/bin/sh
set -eu
: "${APP_KEY:?Set APP_KEY to a stable Laravel key before deploying}"
PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/\*:10000/*:$PORT/" /etc/apache2/sites-available/000-default.conf
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
php artisan db:seed --force
if [ -n "${LODGE_ADMIN_EMAIL:-}" ] && [ -n "${LODGE_ADMIN_PASSWORD:-}" ]; then
    php artisan lodge:admin --from-env
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec apache2-foreground
