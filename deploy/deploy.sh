#!/usr/bin/env bash
# Deploy ulang MGA Online Shop di VPS (jalankan dari folder aplikasi):
#   bash deploy/deploy.sh
# Asumsi: kode diambil dari git, Node.js tersedia untuk build aset.
set -euo pipefail

BRANCH="${BRANCH:-main}"

echo "==> Mode perawatan"
php artisan down --refresh=15 --retry=60 || true

# Pastikan situs kembali hidup walau ada langkah yang gagal.
trap 'php artisan up' EXIT

echo "==> Ambil kode terbaru ($BRANCH)"
git pull --ff-only origin "$BRANCH"

echo "==> Dependency PHP"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "==> Build aset"
npm ci --no-audit --no-fund
npm run build

echo "==> Migrasi database"
php artisan migrate --force

echo "==> Cache config, route, view, event"
php artisan optimize
php artisan storage:link 2>/dev/null || true

echo "==> Restart worker antrean"
php artisan queue:restart

# OPcache: muat ulang PHP-FPM bila user ini punya izin (abaikan bila tidak).
sudo -n systemctl reload php8.3-fpm 2>/dev/null || true

echo "==> Selesai"
php artisan shop:check || true
