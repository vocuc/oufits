#!/usr/bin/env bash
# Cập nhật code mới nhất lên server.
# Dùng:  sudo bash deploy.sh            (lấy nhánh main)
#        sudo BRANCH=dev bash deploy.sh  (lấy nhánh khác)
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
PHP="${PHP:-php8.3}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"

cd "$APP_DIR"
step() { printf '\n\033[1;35m==> %s\033[0m\n' "$*"; }

[ -f .env ] || { echo "Thiếu file .env trong $APP_DIR"; exit 1; }
command -v "$PHP" >/dev/null || PHP=php

# Lỗi giữa chừng thì vẫn mở lại web, không để kẹt ở chế độ bảo trì
trap '"$PHP" artisan up >/dev/null 2>&1 || true; echo; echo "Deploy LỖI — web đã được mở lại, xem lỗi ở trên."' ERR

step "Bật chế độ bảo trì"
"$PHP" artisan down --retry=15 || true

step "Lấy code mới ($BRANCH)"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
git fetch --prune origin
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"   # bỏ mọi sửa tay trên server, giữ nguyên .env và storage (đã gitignore)
git log -1 --format='   %h  %s  (%an, %cr)'

step "Cài thư viện PHP"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

step "Chạy migration"
"$PHP" artisan migrate --force

step "Tạo cache config / route / view"
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan optimize

step "Phân quyền thư mục ghi"
chown -R "$WEB_USER:$WEB_USER" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

step "Nạp lại PHP-FPM"
systemctl reload "$PHP_FPM" 2>/dev/null || echo "   (bỏ qua: không reload được $PHP_FPM)"

step "Tắt chế độ bảo trì"
"$PHP" artisan up

trap - ERR
printf '\n\033[1;32mDeploy xong: %s\033[0m\n' "$(grep -E '^APP_URL=' .env | cut -d= -f2-)"
