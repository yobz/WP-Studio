# Production PHP-FPM image for Railway (or any Dockerfile-based host).
# Separate from docker/php/Dockerfile on purpose — that one is
# deliberately dev-shaped (bind mounts, named volumes seeded at
# container start, a tolerant entrypoint) per
# docs/adr/0013-docker-development-environment.md. This one is
# self-contained: no bind mounts, no dev dependencies, optimized
# autoloader, OPcache on, and a migration failure stops the container
# instead of silently serving a stale schema. See
# docs/adr/0017-cloud-deployment-and-security-hardening.md and
# docs/DEPLOYMENT.md.

# ---- Stage 1: install Composer dependencies in isolation ----
# A separate stage so the final image never needs Composer itself, and
# so this layer stays cached across app-code-only changes.
FROM php:8.3-fpm-alpine AS vendor

WORKDIR /var/www/html

RUN apk add --no-cache \
        sqlite-dev \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        curl-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_sqlite \
        pdo_pgsql \
        mbstring \
        bcmath \
        intl \
        pcntl \
        zip \
        curl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
        --no-interaction \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --ignore-platform-reqs

COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev \
    && composer install --no-interaction --no-dev --optimize-autoloader --ignore-platform-reqs

# ---- Stage 2: the actual runtime image ----
FROM php:8.3-fpm-alpine AS runtime

WORKDIR /var/www/html

# Same extension list as the vendor stage — this is a fresh base layer,
# nothing carries over from stage 1 except what's explicitly COPYed.
# sqlite stays installed even though production targets Postgres
# (docs/adr/0017-cloud-deployment-and-security-hardening.md's Postgres
# decision): identical binary either way keeps SQLite available as a
# fallback without a second image variant to maintain.
RUN apk add --no-cache \
        sqlite-dev \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        curl-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_sqlite \
        pdo_pgsql \
        mbstring \
        bcmath \
        intl \
        pcntl \
        zip \
        curl \
        opcache \
    && apk del --no-cache sqlite-dev postgresql-dev icu-dev libzip-dev curl-dev oniguruma-dev \
    && apk add --no-cache sqlite-libs postgresql-libs icu-libs libzip libcurl oniguruma

COPY docker/production/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --from=vendor /var/www/html ./

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# No Docker-level HEALTHCHECK here — this image only runs PHP-FPM
# (FastCGI, no built-in HTTP listener), so a correct check would need a
# FastCGI client the base image doesn't ship. Railway's own HTTP health
# check, pointed at /api/v1/health (see docs/DEPLOYMENT.md), is the
# real signal — checking through the actual web-facing route this
# service is judged by, not a second, narrower proxy for it.
#
# No `USER www-data` here, deliberately — PHP-FPM's own pool config
# (www.conf's `user`/`group` directives, the base image's default)
# already forks worker processes as www-data; the master process
# starting as root to do that is the base image's own tested,
# unmodified behavior, matching docker/php/Dockerfile's dev image.

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
