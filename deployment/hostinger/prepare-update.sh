#!/usr/bin/env bash
set -euo pipefail
site="${1:?Domain directory required}"
php_bin="${2:-php}"
mode="${3:-update}"
[[ "$mode" == update || "$mode" == initialize ]] || exit 1
[[ "$site" =~ ^/home/[a-zA-Z0-9_-]+/domains/gfmasoniclodge40\.org$ ]] || exit 1
[[ "$php_bin" =~ ^[a-zA-Z0-9/][a-zA-Z0-9/._-]*$ ]] || exit 1
[[ -f "$site/lodge/.env" ]] || { echo 'Upload the private production configuration as lodge/.env first.' >&2; exit 1; }
if [[ "$mode" == initialize ]]; then
    [[ ! -f "$site/lodge/storage/framework/hostinger-initialized" ]] || { echo 'Already initialized; run an ordinary update.' >&2; exit 1; }
    mkdir -p "$site/lodge/bootstrap/cache" "$site/lodge/storage/app/private" \
        "$site/lodge/storage/framework/cache/data" "$site/lodge/storage/framework/sessions" \
        "$site/lodge/storage/framework/views" "$site/lodge/storage/logs" "$site/public_html/storage"
    # The normal Laravel middleware reads this while the first code upload finishes.
    "$php_bin" -r 'file_put_contents($argv[1], json_encode(["except" => [], "redirect" => null, "retry" => 60, "refresh" => null, "secret" => null, "status" => 503, "template" => null]));' "$site/lodge/storage/framework/down"
    exit 0
fi
[[ -f "$site/lodge/.env" && -f "$site/lodge/vendor/autoload.php" && -f "$site/public_html/index.php" ]] || {
    echo 'Use Run workflow with First installation enabled for a fresh site.' >&2
    exit 1
}
cd "$site/lodge"
"$php_bin" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! config("app.key") || config("cache.default") !== "database") {
    fwrite(STDERR, "A stable APP_KEY and CACHE_STORE=database are required.\n");
    exit(1);
}
'
"$php_bin" artisan down --retry=60
# Hold the same lock used by the cron worker, so code is not replaced mid-send.
# The database lock survives this process and expires if deployment is interrupted.
umask 077
"$php_bin" -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$lock = Illuminate\Support\Facades\Cache::lock("lodge-mail-queue", 1800);
try { $lock->block(90); } catch (Throwable $e) {
    fwrite(STDERR, "The email worker did not finish in time; deployment stopped.\n");
    exit(1);
}
file_put_contents("storage/framework/hostinger-deploy-lock", $lock->owner());
'
