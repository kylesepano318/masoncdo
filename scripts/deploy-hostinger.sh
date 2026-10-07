#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
package="${1:?Provide the built package directory}"
site="${HOSTINGER_SITE_PATH:?Set HOSTINGER_SITE_PATH}"
php_bin="${HOSTINGER_PHP_BIN:-php}"
mode="${HOSTINGER_DEPLOY_MODE:-update}"
[[ "$mode" == update || "$mode" == initialize ]] || { echo "Invalid deployment mode." >&2; exit 1; }
# Validate before sending any remote command, particularly rsync --delete.
[[ "$site" =~ ^/home/[a-zA-Z0-9_-]+/domains/gfmasoniclodge40\.org$ ]] || { echo "Invalid Hostinger domain directory." >&2; exit 1; }
[[ "$php_bin" =~ ^[a-zA-Z0-9/][a-zA-Z0-9/._-]*$ ]] || { echo "Invalid PHP binary." >&2; exit 1; }
[[ -f "$package/lodge/vendor/autoload.php" && -f "$package/public_html/index.php" ]] || { echo "Incomplete deployment package." >&2; exit 1; }
ssh hostinger "bash -s -- '$site' '$php_bin' '$mode'" < deployment/hostinger/prepare-update.sh
# Excluded directories are protected from --delete. Never use --delete-excluded.
rsync -az --delete --exclude='/.env' --exclude='/storage/***' --exclude='/bootstrap/cache/***' \
    "$package/lodge/" "hostinger:$site/lodge/"
rsync -az --delete --exclude='/storage/***' --exclude='/.well-known/***' \
    --exclude='/cgi-bin/***' --exclude='/.user.ini' --exclude='/php.ini' \
    "$package/public_html/" "hostinger:$site/public_html/"
rsync -az "$package/public_html/storage/.htaccess" "hostinger:$site/public_html/storage/.htaccess"
ssh hostinger "bash -s -- '$site' '$php_bin' '$mode'" < deployment/hostinger/finish-update.sh
