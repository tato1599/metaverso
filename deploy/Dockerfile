# Imagen de producción del backend Metaverso Escolar TecNM.
# PHP 8.3 + Apache (mod_php) sirviendo public/. Construir desde la raíz del repo:
#   docker compose -f deploy/docker-compose.prod.yml up -d --build

# --- Etapa 1: compilar el front (Inertia/React + Tailwind + portada) ---
# El panel es una app React/Inertia; sin esta etapa no existe public/build/manifest.json
# y la app se cae en produccion. Las fuentes quedan embebidas: el on-premise no
# necesita internet.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- Etapa 2: la aplicación ---
FROM php:8.3-apache

# --- Extensiones de PHP requeridas por Laravel + PostgreSQL ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libonig-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql mbstring bcmath zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Apache: docroot en public/ + mod_rewrite + AllowOverride ---
RUN a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf
RUN printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
