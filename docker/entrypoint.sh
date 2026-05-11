#!/bin/bash

cd /var/www/html

echo "==> Clearing Symfony cache..."
php bin/console cache:clear --env=prod --no-warmup || true

echo "==> Running Doctrine schema update..."
php bin/console doctrine:schema:update --force --complete --no-interaction --env=prod 2>&1
SCHEMA_EXIT=$?
if [ $SCHEMA_EXIT -ne 0 ]; then
    echo "WARNING: schema:update exited $SCHEMA_EXIT, trying schema:create..."
    php bin/console doctrine:schema:create --no-interaction --env=prod 2>&1 || true
fi

echo "==> Warming up Symfony cache..."
php bin/console cache:warmup --env=prod 2>&1 || true

echo "==> Fixing var/ permissions (after cache creation)..."
mkdir -p /var/www/html/var/cache /var/www/html/var/log
chown -R www-data:www-data /var/www/html/var
chmod -R 775 /var/www/html/var

echo "==> Starting Apache..."
exec apache2-foreground
