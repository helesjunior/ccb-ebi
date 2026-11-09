# Imagem única do backend: PHP 8.4 + FrankenPHP (Octane). Laravel 13 exige PHP >= 8.4.
FROM dunglas/frankenphp:1-php8.4

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip libpq-dev libzip-dev libicu-dev libonig-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && install-php-extensions \
        pdo_pgsql pgsql zip intl mbstring bcmath pcntl opcache gd exif redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app