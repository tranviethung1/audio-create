#!/usr/bin/env bash
# Chạy toàn bộ LanhVCL: Postgres + TTS service (Docker) + web + queue worker.
set -euo pipefail
cd "$(dirname "$0")"

export DOCKER_UID="${DOCKER_UID:-$(id -u)}" DOCKER_GID="${DOCKER_GID:-$(id -g)}"

docker compose up -d
php artisan migrate --force

# Nạp thêm php-ini/uploads.ini (dấu ":" đầu giữ nguyên thư mục ini mặc định) để upload được file lớn
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$PWD/php-ini"

trap 'kill 0' EXIT
php artisan queue:work --tries=1 --timeout=0 &
php artisan serve --host=127.0.0.1 --port="${PORT:-8088}"
