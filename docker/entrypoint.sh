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

# Stand des Images in die Volumes bringen (docker-compose.prod.yml). Ein
# Volume wird nur beim ersten Start aus dem Image befüllt; danach verdeckt
# es, was ein neueres Image mitbringt. Im Entwicklungs-Setup ist plugins/
# kein eigener Mount, sondern Teil des Bind-Mounts, dort bleibt alles wie es ist.
SHIPPED=/usr/local/share/ignis
if [ -d "$SHIPPED/plugins" ] && mountpoint -q /var/www/html/plugins; then
    # Mitgelieferte Plugins ganz ersetzen, damit auch gelöschte Dateien
    # verschwinden. Katalog-Plugins samt .installed bleiben unberührt.
    for dir in "$SHIPPED"/plugins/*/; do
        rm -rf "/var/www/html/plugins/$(basename "$dir")"
    done
    # Früher mitgeliefert, inzwischen in einem anderen Plugin aufgegangen
    rm -rf /var/www/html/plugins/enotf-v2
    cp -a "$SHIPPED/plugins/." /var/www/html/plugins/
    # Der Katalog-Installer legt neue Plugin-Ordner als www-data an.
    chown www-data:www-data /var/www/html/plugins
    echo "[entrypoint] Mitgelieferte Plugins aus dem Image übernommen"
fi

# version.json gehört zum Image. Wechselt die Version, ist auch der Cache
# (Routen, Suchvokabular, Update-Prüfung) vom alten Stand und fliegt raus.
# Im Entwicklungs-Setup ist sie aus dem Checkout eingehängt und bleibt.
if [ -f "$SHIPPED/version.json" ] && ! mountpoint -q /var/www/html/storage/version.json \
    && ! cmp -s "$SHIPPED/version.json" /var/www/html/storage/version.json; then
    cp "$SHIPPED/version.json" /var/www/html/storage/version.json
    chown www-data:www-data /var/www/html/storage/version.json
    find /var/www/html/storage/cache -mindepth 1 -delete
    echo "[entrypoint] version.json aus dem Image übernommen, Cache geleert"
fi

# Wait for database to be ready. Die Zugangsdaten liest PHP selbst aus der
# Umgebung; in den Code eingesetzt würde ein Anführungszeichen im Passwort
# das Snippet zerbrechen.
if [ -n "$DB_HOST" ]; then
    echo "[entrypoint] Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
    max_tries=30
    count=0
    until php -r '
        $port = (int) getenv("DB_PORT") ?: 3306;
        try { new PDO("mysql:host=" . getenv("DB_HOST") . ";port=" . $port, getenv("DB_USER"), getenv("DB_PASS")); }
        catch (Exception $e) { exit(1); }
    ' 2>/dev/null; do
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

# Run database migrations (Phinx via tools/db-migrate.php: bridge + migrate)
echo "[entrypoint] Running database migrations..."
cd /var/www/html && php tools/db-migrate.php || echo "[entrypoint] WARNING: Migration had issues (may be normal on first run)"

# Eine Hosting-Verwaltung (fabrica) gibt die Adresse vor. Dann gilt sie und
# ist in den Einstellungen nicht mehr änderbar, sonst wäre sie beim nächsten
# Start ohnehin wieder überschrieben.
if [ -n "$SYSTEM_URL" ]; then
    php -r '
        require "/var/www/html/vendor/autoload.php";
        $url = preg_replace("#^https?://#i", "", rtrim(getenv("SYSTEM_URL"), "/"));
        $pdo = new PDO(mysql_dsn(), getenv("DB_USER"), getenv("DB_PASS"));
        $pdo->prepare("UPDATE intra_config SET config_value = ?, is_editable = 0 WHERE config_key = ?")->execute([$url, "SYSTEM_URL"]);
    ' && echo "[entrypoint] SYSTEM_URL aus der Umgebung übernommen" \
      || echo "[entrypoint] WARNING: SYSTEM_URL konnte nicht gesetzt werden"
fi

echo "[entrypoint] Starting Apache..."
exec "$@"
