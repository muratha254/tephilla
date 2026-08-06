#!/bin/bash
# Run this in cPanel Terminal after cloning the repo.
# Usage:
#   cd ~/delite.lewsoftech.com   # or your subdomain folder
#   bash scripts/deploy-cpanel.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

echo "==> Deploying TrackIgniter from: $APP_DIR"

if [[ ! -f .env ]]; then
  if [[ -f .env.example ]]; then
    cp .env.example .env
    echo "Created .env from .env.example — edit DB credentials before continuing."
  else
    echo "ERROR: .env not found. Create it first."
    exit 1
  fi
fi

# PHP binary (cPanel often provides /usr/local/bin/ea-php81)
PHP_BIN="${PHP_BIN:-php}"
if command -v ea-php81 >/dev/null 2>&1; then
  PHP_BIN=ea-php81
elif command -v ea-php80 >/dev/null 2>&1; then
  PHP_BIN=ea-php80
fi

echo "==> Using PHP: $($PHP_BIN -v | head -1)"

if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: composer not found. Install Composer in cPanel or run:"
  echo "  curl -sS https://getcomposer.org/installer | $PHP_BIN"
  exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction

if grep -q '^APP_KEY=$' .env || grep -q '^APP_KEY=$' .env 2>/dev/null; then
  $PHP_BIN artisan key:generate --force
fi

$PHP_BIN artisan storage:link 2>/dev/null || true
$PHP_BIN artisan migrate --force
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

echo ""
echo "Done. Verify:"
echo "  1. Document root is EITHER:"
echo "       $APP_DIR/public   (recommended)"
echo "     OR"
echo "       $APP_DIR          (uses root index.php + .htaccess)"
echo "  2. APP_URL in .env is https://delite.lewsoftech.com"
echo "  3. Visit https://delite.lewsoftech.com"
echo ""
echo "If you still see a plain '404 Not Found' page (not Laravel):"
echo "  - Subdomain document root folder is wrong or files are not uploaded"
echo "  - In cPanel > Domains, confirm delite.lewsoftech.com points to this folder"
