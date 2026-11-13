# Clinic Queue API: Laravel on PHP 8.5 (built-in server is fine for a demo; use FPM + nginx in production)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM php:8.5-cli-alpine
RUN apk add --no-cache postgresql-dev sqlite-dev && docker-php-ext-install pdo_pgsql pdo_sqlite
WORKDIR /app
COPY --from=vendor /app /app
RUN mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views && chmod -R 775 storage bootstrap/cache && APP_KEY=base64:dGVtcG9yYXJ5LWtleS1mb3ItZGlzY292ZXJ5LTMyYg== php artisan package:discover
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr DB_CONNECTION=sqlite DB_DATABASE=/app/database/database.sqlite
EXPOSE 8000
CMD sh -c 'touch "$DB_DATABASE" 2>/dev/null; [ -n "$APP_KEY" ] || export APP_KEY=$(php artisan key:generate --show); php artisan migrate --force && { [ "$SEED_DEMO" = "1" ] && php artisan db:seed --force || true; } && php artisan serve --host 0.0.0.0 --port ${PORT:-8000}'
