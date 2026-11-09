# Imagem única da aplicação: PHP 8.4 + FrankenPHP (Octane). Laravel 13 exige PHP >= 8.4.
# É a mesma imagem em dev e em produção (single artifact, promovível por digest):
# o compose de dev monta o código por cima; a produção usa o código já embutido.
FROM dunglas/frankenphp:1-php8.4

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip zip libpq-dev libzip-dev libicu-dev libonig-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && install-php-extensions \
        pdo_pgsql pgsql zip intl mbstring bcmath pcntl opcache gd exif redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependências de produção resolvidas a partir do lockfile (camada cacheável).
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-autoloader --no-scripts

# Código da aplicação (o .dockerignore da raiz exclui vendor/, .env, etc.).
COPY backend/ ./

# Autoload otimizado e descoberta de pacotes com o código já presente.
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan package:discover --ansi

# Diretórios que o Laravel escreve em runtime; em produção o contêiner é read-only
# e monta tmpfs em /tmp, mas o storage precisa existir e ser gravável no build.
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache