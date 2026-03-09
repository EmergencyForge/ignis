#!/bin/bash
set -e

# Ensure storage directories exist and are writable
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/cache \
         /var/www/html/storage/documents \
         /var/www/html/storage/temp \
         /var/www/html/uploads

chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads

# In development mode: enable OPcache timestamps and display_errors
if [ "$APP_ENV" = "development" ]; then
    echo "opcache.validate_timestamps = 1" >> "$PHP_INI_DIR/conf.d/99-intrarp.ini"
    echo "display_errors = On" >> "$PHP_INI_DIR/conf.d/99-intrarp.ini"
    echo "[entrypoint] Development mode enabled"
fi

# Wait for database to be ready
if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Waiting for database at $DB_HOST..."
    max_tries=30
    count=0
    until php -r "try { new PDO('mysql:host=${DB_HOST}', '${DB_USER}', '${DB_PASS}'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; do
        count=$((count + 1))
        if [ $count -ge $max_tries ]; then
            echo "[entrypoint] WARNING: Database not reachable after ${max_tries} attempts, starting anyway..."
            break
        fi
        echo "[entrypoint] Database not ready yet... ($count/$max_tries)"
        sleep 2
    done
    echo "[entrypoint] Database connection established"
fi

# Run database migrations
echo "[entrypoint] Running database migrations..."
cd /var/www/html && php setup/database-init.php || echo "[entrypoint] WARNING: Migration had issues (may be normal on first run)"

echo "[entrypoint] Starting Apache..."
exec "$@"
