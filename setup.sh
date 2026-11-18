#!/usr/bin/env bash
# Instalasi pertama (Linux/macOS). Windows: jalankan perintah di README satu per satu.
set -euo pipefail

[ -f .env ] || cp .env.example .env
composer install
php artisan key:generate
echo ">> Edit .env (DB_*, SEED_*_PASSWORD) lalu tekan Enter"; read -r _
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
npm install
npm run build
echo "Selesai. Jalankan: php artisan serve   (admin: /admin, anggota: /anggota)"
