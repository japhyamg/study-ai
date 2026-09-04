# syntax=docker/dockerfile:1
#
# Study AI — container image for Render (or any Docker host).
#
# Stage 1 builds the frontend assets with Vite, stage 2 runs PHP.

# ---------- Stage 1: frontend assets ----------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
# Blade files are scanned by Tailwind's content globs.
COPY app ./app

RUN npm run build


# ---------- Stage 2: PHP runtime ----------
FROM php:8.3-cli-alpine AS app

# pdo_pgsql for Render Postgres, pdo_sqlite + zip/intl for general use.
RUN apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_sqlite intl zip bcmath opcache \
    && apk del postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && apk add --no-cache libpq icu-libs libzip

# Opcache settings suited to a small always-warm container.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=0'; \
      echo 'opcache.memory_consumption=64'; \
      echo 'opcache.max_accelerated_files=10000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first so this layer caches independently of app code.
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --prefer-dist \
      --no-scripts \
      --no-autoloader

COPY . .

# Drop artifacts that must never ship in the image.
RUN rm -f .env database/database.sqlite teacher_cookies.txt

COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && chmod -R ug+w storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=10000

EXPOSE 10000

ENTRYPOINT ["entrypoint"]
