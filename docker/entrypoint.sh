#!/bin/bash
set -e

echo "==> Starting Java Indonusa ISP Billing..."

# Wait for MySQL
echo "==> Waiting for MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST:-mysql};port=${DB_PORT:-3306}', '${DB_USERNAME:-javaindonusa}', '${DB_PASSWORD:-secret}');" 2>/dev/null; do
    sleep 2
done
echo "==> MySQL is ready."

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force

# Cache config, routes, views
echo "==> Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link if not exists
php artisan storage:link 2>/dev/null || true

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start services
echo "==> Starting PHP-FPM..."
php-fpm -D

echo "==> Starting Nginx..."
nginx -g "daemon off;" &

echo "==> Starting queue worker..."
php artisan queue:work redis --queue=default,notifications --sleep=3 --tries=3 --max-time=3600 &

echo "==> All services started."
wait
