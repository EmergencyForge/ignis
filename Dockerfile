FROM php:8.3-apache

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

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
