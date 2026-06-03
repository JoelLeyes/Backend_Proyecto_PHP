FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    ca-certificates \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libzstd-dev \
        unzip \
        git \
        postgresql-client \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl bcmath pcntl \
    && pecl install redis \
    && pecl install mongodb \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable mongodb \
    && update-ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer update --no-dev --no-scripts --no-autoloader --ignore-platform-req=ext-mongodb

COPY . .
RUN composer dump-autoload --optimize --ignore-platform-req=ext-mongodb \
    && sed -i 's/\r$//' docker-entrypoint.sh \
    && chmod +x docker-entrypoint.sh

EXPOSE 8000 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
