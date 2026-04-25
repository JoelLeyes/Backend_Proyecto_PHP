FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
        postgresql-client \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize \
    && chmod +x docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
