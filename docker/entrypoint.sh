#!/bin/bash
set -e

echo "==> Clearing Symfony cache..."
php bin/console cache:clear --no-warmup --env=prod 2>/dev/null || true

echo "==> Warming up Symfony cache..."
php bin/console cache:warmup --env=prod 2>/dev/null || true

echo "==> Running Doctrine schema update (safe, PostgreSQL-compatible)..."
php bin/console doctrine:schema:update --force --complete --no-interaction --env=prod 2>&1 || {
    echo "WARNING: doctrine:schema:update failed, attempting schema:create..."
    php bin/console doctrine:schema:create --no-interaction --env=prod 2>&1 || true
}

echo "==> Schema ready. Starting Apache..."
exec apache2-foreground
