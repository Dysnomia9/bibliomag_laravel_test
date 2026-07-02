FROM php:8.3-cli

# Dependencias del sistema + extensiones PHP necesarias para Laravel + Postgres
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer 2.8+ audita advisories de seguridad por defecto y puede bloquear
# instalaciones legítimas de Laravel; lo desactivamos a nivel de imagen.
ENV COMPOSER_NO_AUDIT=1

WORKDIR /var/www

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY app-overlay /app-overlay

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
