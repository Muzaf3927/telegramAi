# syntax=docker/dockerfile:1

FROM php:8.2-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    bash \
    icu-dev \
    libpq-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    shadow \
    nodejs \
    npm \
  && docker-php-ext-install \
    intl \
    mbstring \
    zip \
    pdo \
    pdo_pgsql \
    bcmath \
  && apk del --no-cache --purge

# Configure working directory
WORKDIR /var/www/html

# Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy app files
COPY . /var/www/html

# Ensure storage/bootstrap cache are writable
RUN set -eux; \
  addgroup -g 1000 -S www; \
  adduser -u 1000 -S www -G www; \
  chown -R www:www /var/www/html; \
  mkdir -p storage framework bootstrap/cache; \
  chown -R www:www storage bootstrap/cache; \
  chmod -R 775 storage bootstrap/cache

# Install PHP dependencies
USER www
RUN composer install --no-interaction --prefer-dist --no-progress

# Optionally install JS deps if present
RUN if [ -f package.json ]; then npm ci --no-audit --fund=false || true; fi

# Expose php-fpm port
EXPOSE 9000

CMD ["php-fpm"]


