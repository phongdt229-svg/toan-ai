#!/usr/bin/env bash
#
# Phát hành phiên bản mới lên production.
#
#   sudo -u www-data bash deploy/deploy.sh
#
# Trang bảo trì (resources/views/errors/503.blade.php) được CHỤP SẴN thành HTML tĩnh lúc
# `artisan down` — nên người dùng vẫn thấy trang tử tế kể cả khi đang composer install,
# npm build hay migrate dở dang. Người deploy vẫn vào xem được site thật bằng link bí mật.
#
# Nếu có bước nào hỏng, script dừng ngay (set -e) nhưng vẫn gỡ bảo trì (trap) để không
# bỏ quên site ở trạng thái 503.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/toan-ai}"
BRANCH="${BRANCH:-main}"
# Link xem trước khi đang bảo trì: https://toanai.vn/<SECRET>
SECRET="${DEPLOY_SECRET:-$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')}"

cd "$APP_DIR"

finish() {
    php artisan up || true
}
trap finish EXIT

echo "==> Bật trang bảo trì"
# Thời gian dự kiến đọc qua config; config đang cache thì DEPLOY_ETA mới sẽ không có tác dụng.
php artisan config:clear
php artisan down --render="errors::503" --secret="$SECRET" --retry=60
# Lấy APP_URL thẳng từ .env: `artisan tinker` là gói dev, production cài --no-dev nên không có.
APP_URL="$(grep -m1 '^APP_URL=' .env | cut -d= -f2- | tr -d '"' || true)"
echo "    Xem trước bản thật tại: ${APP_URL:-https://toanai.vn}/$SECRET"

echo "==> Lấy code mới"
git pull --ff-only origin "$BRANCH"

echo "==> Cài đặt phụ thuộc"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

echo "==> Migrate + đồng bộ dữ liệu nền"
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=PackageSeeder --force

echo "==> Nạp lại cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Khởi động lại worker"
php artisan queue:restart

echo "==> Kiểm tra nhanh trước khi mở lại"
php artisan about --only=environment

# trap finish sẽ chạy `php artisan up`
echo "==> Xong. Gỡ bảo trì."
