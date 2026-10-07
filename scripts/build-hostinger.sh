#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
target="${1:?Provide an empty staging directory}"
[[ ! -e "$target" ]] || { echo "Staging directory must not exist." >&2; exit 1; }
mkdir -p "$target/lodge/bootstrap/cache" "$target/public_html/storage"
for folder in app config database resources routes; do
    cp -R "backend/$folder" "$target/lodge/"
done
cp backend/bootstrap/app.php backend/bootstrap/providers.php "$target/lodge/bootstrap/"
cp backend/artisan backend/composer.json backend/composer.lock "$target/lodge/"
cp deployment/hostinger/.env.example "$target/lodge/.env.example"
cp -R frontend/dist/. "$target/public_html/"
cp deployment/hostinger/index.php "$target/public_html/index.php"
cp deployment/hostinger/public.htaccess "$target/public_html/.htaccess"
cp deployment/hostinger/storage.htaccess "$target/public_html/storage/.htaccess"
# Do not discover packages using the build machine's configuration.
composer install --working-dir="$target/lodge" --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
composer check-platform-reqs --working-dir="$target/lodge" --no-dev
