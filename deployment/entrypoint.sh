#!/bin/sh
set -eu
: "${APP_KEY:?Set APP_KEY to a stable Laravel key before deploying}"
PORT="${PORT:-10000}"
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown www-data:www-data storage storage/app storage/app/public storage/framework storage/framework/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [ "${SERVICE_ROLE:-web}" = "worker" ]; then
    php artisan config:cache
    exec php artisan queue:work database --sleep=3 --timeout=45 --tries=5
fi
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/\*:10000/*:$PORT/" /etc/apache2/sites-available/000-default.conf
php artisan migrate --force
php artisan db:seed --force
if [ -n "${LODGE_ADMIN_EMAIL:-}" ] && [ -n "${LODGE_ADMIN_PASSWORD:-}" ]; then
    php artisan lodge:admin --from-env
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec apache2-foreground
