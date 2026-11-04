#!/bin/sh
set -e
cd /var/www/html

# Render tells the container which port to listen on (default 10000)
PORT="${PORT:-10000}"
sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Use the public Render URL if APP_URL was not set
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"

if [ -z "$APP_KEY" ]; then
    echo "WARNING: APP_KEY is not set. Using a temporary key that changes on every restart."
    echo "Set APP_KEY in the Render dashboard (php artisan key:generate --show)."
    export APP_KEY="$(php artisan key:generate --show)"
fi

# Create / update the database tables (wait while the database wakes up)
i=0
until php artisan migrate --force; do
    i=$((i + 1))
    if [ "$i" -ge 10 ]; then
        echo "Migrations failed after 10 tries. Check the database settings."
        exit 1
    fi
    echo "Database not ready, retrying in 5 seconds ($i/10)..."
    sleep 5
done

# Categories, platforms and the first admin user. Safe to repeat.
# Must run before config:cache, because the seeders read env values.
php artisan db:seed --force

php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

exec apache2-foreground
