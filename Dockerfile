FROM php:8.2-apache

# System deps: libpq-dev for pdo_pgsql, libzip-dev for zip, libicu-dev for intl
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite (required by Symfony)
RUN a2enmod rewrite

# Set document root to Symfony's public/ folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides (needed for Symfony's front controller routing)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency files first (layer cache: re-install only when these change)
COPY composer.json composer.lock symfony.lock ./

# Install prod dependencies (no dev tools, no scripts that need the full app)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy the rest of the application
COPY . .

# Optimise autoloader now that all files are present
RUN composer dump-autoload --no-dev --classmap-authoritative

# Symfony production warm-up
RUN APP_ENV=prod php bin/console cache:warmup --no-debug || true

RUN mkdir -p var && chmod -R 775 var
# Fix file permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/public \
    && chmod -R 775 /var/www/html/var

# Entrypoint: run schema update then start Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
