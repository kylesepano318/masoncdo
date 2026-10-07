#!/usr/bin/env bash
set -euo pipefail
site="${1:?Domain directory required}"
php_bin="${2:-php}"
mode="${3:-update}"
[[ "$mode" == update || "$mode" == initialize ]] || exit 1
[[ "$site" =~ ^/home/[a-zA-Z0-9_-]+/domains/gfmasoniclodge40\.org$ ]] || exit 1
[[ "$php_bin" =~ ^[a-zA-Z0-9/][a-zA-Z0-9/._-]*$ ]] || exit 1
cd "$site/lodge"
# Remove generated PHP caches before bootstrapping the new dependencies.
"$php_bin" -r 'foreach (glob("bootstrap/cache/*.php") as $file) { if (! unlink($file)) { exit(1); } }'
if [[ "$mode" == initialize ]]; then
    key_status=0
    "$php_bin" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (! $app->environment("production") || config("app.debug")) {
        fwrite(STDERR, "Set APP_ENV=production and APP_DEBUG=false.\n");
        exit(2);
    }
    exit(config("app.key") ? 0 : 1);
    ' || key_status=$?
    if [[ "$key_status" == 1 ]]; then
        "$php_bin" artisan key:generate --force
    elif [[ "$key_status" != 0 ]]; then
        exit "$key_status"
    fi
fi
"$php_bin" artisan package:discover --ansi
"$php_bin" artisan migrate --force
if [[ "$mode" == initialize ]]; then
    "$php_bin" artisan db:seed --force
fi
"$php_bin" artisan config:cache
"$php_bin" artisan route:cache
"$php_bin" artisan view:cache
"$php_bin" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$file = "storage/framework/hostinger-deploy-lock";
if (is_file($file)) {
    Illuminate\Support\Facades\Cache::restoreLock("lodge-mail-queue", trim(file_get_contents($file)))->release();
    unlink($file);
}
'
if [[ "$mode" == initialize ]]; then
    touch storage/framework/hostinger-initialized
fi
"$php_bin" artisan up
printf 'Hostinger deployment completed.\n'
