# BattleSnake server — deterministic image for DigitalOcean App Platform.
FROM php:8.3-apache

# System packages: git + unzip let Composer extract dependencies.
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

# Slim's front controller relies on mod_rewrite.
RUN a2enmod rewrite

# App Platform routes to http_port 8080 by default; serve Apache there.
RUN sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf

# OPcache — required in production per
# spec/decisions/003-deployment-and-latency-budget.md
RUN docker-php-ext-install opcache \
 && { \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.memory_consumption=64'; \
      echo 'opcache.max_accelerated_files=10000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Virtual host: document root at public/, front-controller rewrite baked in.
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Download dependencies first; this layer is cached unless composer.* changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader

# Application code.
COPY . .

# Build the optimized, authoritative autoloader now that src/ is present.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

EXPOSE 8080
