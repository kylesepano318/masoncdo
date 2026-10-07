FROM node:22-bookworm-slim AS frontend-build
WORKDIR /app
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_API_BASE_URL=""
RUN npm run typecheck && npm run build

FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libonig-dev libicu-dev unzip git \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install pdo_pgsql pdo_mysql zip gd mbstring intl bcmath opcache pcntl \
 && a2enmod rewrite headers expires deflate \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY backend/ ./
COPY --from=frontend-build /app/dist/ ./public/
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache
COPY deployment/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deployment/php.ini /usr/local/etc/php/conf.d/lodge.ini
COPY deployment/entrypoint.sh /usr/local/bin/lodge-start
RUN sed -i 's/\r$//' /usr/local/bin/lodge-start && chmod +x /usr/local/bin/lodge-start
EXPOSE 10000
CMD ["lodge-start"]
