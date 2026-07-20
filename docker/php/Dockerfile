# PHP-FPM image shared by the backend, queue, and scheduler services —
# they differ only in the command each container runs (see docker-compose.yml).
# See docs/adr/0013-docker-development-environment.md for why this is a
# small, hand-written image rather than Laravel Sail's Ubuntu-based one.
FROM php:8.3-fpm-alpine

WORKDIR /var/www/html

# Extensions this application actually requires, each tied to a real
# need — not a general-purpose "every Laravel app might want this" list:
#   pdo_sqlite / sqlite3 — the database driver (docs/adr/0004-backend-foundation.md)
#   mbstring, bcmath      — Laravel framework baseline requirements
#   intl                  — Laravel's translation/pluralization
#   pcntl                 — graceful signal handling for `queue:work`
#   zip                   — Composer package extraction
#   curl                  — every outbound call this app makes (WordPress
#                           integration, Anthropic/Gemini clients). OpenSSL
#                           support is already compiled into the base image
#                           and needs no extra extension install.
RUN apk add --no-cache \
        sqlite-dev \
        icu-dev \
        libzip-dev \
        curl-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_sqlite \
        mbstring \
        bcmath \
        intl \
        pcntl \
        zip \
        curl \
    && apk del --no-cache sqlite-dev icu-dev libzip-dev curl-dev oniguruma-dev \
    && apk add --no-cache sqlite-libs icu-libs libzip libcurl oniguruma

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Build context is the repo root (see docker-compose.yml) so this
# Dockerfile can reach both backend/ and its own entrypoint script
# under docker/php/ — Docker's COPY cannot reach outside its context.

# Dependencies are installed at build time so the `vendor` named volume
# (see docker-compose.yml) is seeded from the image on first container
# start — re-run `composer install` inside the container after changing
# composer.json/composer.lock (see the ADR's Volume Strategy section).
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --ignore-platform-reqs

COPY backend/ ./
RUN composer dump-autoload --optimize

# Directories copied from a Windows/NTFS build context can come out of
# the image layer without a write bit (no Unix permission bits exist
# on NTFS for Docker Desktop to preserve) — confirmed during this
# milestone's own live validation: storage/app and storage/framework's
# subdirectories built read-only, and Laravel's session/view-cache
# writes to them failed with a tempnam() error. www-data (the PHP-FPM
# worker user) needs real write access regardless of what the build
# context's host filesystem could express.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
