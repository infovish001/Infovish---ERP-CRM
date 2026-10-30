ARG IMAGE_REGISTRY=docker.io/library
FROM ${IMAGE_REGISTRY}/node:24-alpine AS assets
WORKDIR /source/backend
COPY backend/package*.json ./
RUN npm ci
COPY backend/ ./
RUN npm run build

FROM ${IMAGE_REGISTRY}/composer:2 AS composer-cli

FROM ${IMAGE_REGISTRY}/php:8.5-cli-alpine
RUN apk add --no-cache libpq libzip icu-libs oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev linux-headers oniguruma-dev \
    && docker-php-ext-install pdo_pgsql bcmath pcntl zip intl mbstring \
    && apk del .build-deps
COPY --from=composer-cli /usr/bin/composer /usr/bin/composer
WORKDIR /app/backend
COPY backend/ ./
RUN composer install --no-interaction --prefer-dist --no-progress \
    && addgroup -g 10001 erp && adduser -D -u 10001 -G erp erp \
    && chown -R erp:erp storage bootstrap/cache
COPY --from=assets /source/backend/public/build ./public/build
USER erp
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
