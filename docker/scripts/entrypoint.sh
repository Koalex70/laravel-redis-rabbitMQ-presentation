#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f artisan ]; then
  # Allow bootstrap when repo keeps src tracked via .gitkeep.
  if [ -f .gitkeep ] && [ "$(find . -mindepth 1 -maxdepth 1 | wc -l | tr -d ' ')" = "1" ]; then
    rm -f .gitkeep
  fi

  if [ "$(find . -mindepth 1 -maxdepth 1 | wc -l | tr -d ' ')" = "0" ]; then
    echo "Laravel project not found. Creating latest Laravel..."
    composer create-project laravel/laravel .
  else
    echo "Laravel project not found, but /var/www/html is not empty."
    echo "Skip auto-bootstrap. Mount an existing Laravel app in ./src or empty the folder."
  fi
fi

if [ -f composer.json ] && [ ! -d vendor ]; then
  echo "Installing PHP dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -f .env.example ] && [ ! -f .env ]; then
  cp .env.example .env
fi

if [ -f artisan ]; then
  php artisan key:generate --force >/dev/null 2>&1 || true
fi

if [ "${FRONTEND_BUILD_ON_START:-true}" = "true" ] && [ -f package.json ]; then
  echo "Installing Node.js dependencies in container..."
  npm install --no-audit --no-fund --include=optional
  echo "Building frontend assets..."
  if ! npm run build; then
    echo "Initial frontend build failed, retrying with clean node_modules..."
    rm -rf node_modules
    npm install --no-audit --no-fund --include=optional
    npm run build
  fi
fi

exec "$@"
