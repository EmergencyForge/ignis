FROM php:8.3-apache

# Internal capability for EmergencyForge-managed Fabrica deployments.
LABEL de.emergencyforge.fabrica.login="1"

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    gd \
    intl \
    curl \
    zip \
    opcache \
    xml

# Apache modules
RUN a2enmod rewrite headers

# PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-intrarp.ini"

# Apache VirtualHost
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Composer bleibt im Image: die Update-Routine und tools/db-migrate.php
# rufen ihn zur Laufzeit auf.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Application
#
# vendor/ kommt fertig mit hinein und wird hier nicht mehr gebaut: seit die
# gemeinsamen Pakete als Pfad-Repository aus dem Nachbar-Repo WebPackages
# kommen, braeuchte ein composer install im Image einen Build-Context, der
# beide Repos umfasst. Stattdessen installiert der Workflow (image.yml) die
# Abhaengigkeiten auf dem Runner und loest die Symlinks vorher auf; im
# Compose-Setup liegt vendor/ ohnehin im Bind-Mount vom Host.
WORKDIR /var/www/html
COPY . .

# Storage directories
RUN mkdir -p storage/logs storage/cache storage/documents storage/temp uploads \
    && chown -R www-data:www-data storage uploads \
    && chmod -R 775 storage uploads

# Der Stand des Images, unabhaengig von den Volumes. Liegen storage/ und
# plugins/ in Volumes, verdecken sie nach einem Image-Wechsel die neue
# version.json und die neuen mitgelieferten Plugins. Der Entrypoint
# gleicht beides von hier ab.
RUN mkdir -p /usr/local/share/ignis \
    && cp -a plugins /usr/local/share/ignis/plugins \
    && cp -a storage/version.json /usr/local/share/ignis/version.json

# Kennzeichnet das offizielle Image. Der Updater installiert hier nichts,
# sondern verweist auf ein neues Image (SystemUpdater::runsInContainer).
ENV IGNIS_RUNTIME=docker

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

# /healthz antwortet mit 503, wenn Datenbank oder Migrationen fehlen. Die
# Startphase deckt das Warten auf die Datenbank und den Migrationslauf ab.
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/healthz || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
