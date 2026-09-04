# One image, one origin: the compiled SPA and the API ship together and are served from the
# same host. That is not only tidy — the refresh token is a cookie scoped to /api/v1/token,
# and a single origin means it works without any CORS credentials negotiation at all.

# --- the single-page application ------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

# Dependencies first, so a change to the source does not re-download the world.
# pnpm arrives pinned to the version package.json names, rather than through corepack: a
# build-time download of the package manager itself is one more thing that can fail, and the
# version is already stated in one place.
RUN npm i -g pnpm@10.30.2

COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY frontend/ ./
RUN pnpm run build


# --- the API, and the web server in front of it ---------------------------------------
# FrankenPHP is Caddy with PHP embedded: one process serves the static SPA assets and the
# API, handles HTTP/2 and — from Stage 8 — carries a Mercure hub without a second container.
FROM dunglas/frankenphp:php8.2 AS runtime

# install-php-extensions ships with the image and resolves the build dependencies itself.
RUN install-php-extensions pdo_pgsql intl zip opcache

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# The dependency layer, cached until the lock file itself changes. `--no-scripts` because the
# Symfony scripts want the application code, which is not here yet.
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY backend/ ./

# The SPA lands in Symfony's document root: Caddy serves the hashed assets straight off disk,
# and anything it cannot find falls through to PHP, where SpaController returns index.html.
COPY --from=frontend /app/dist/. ./public/

RUN composer dump-autoload --no-dev --classmap-authoritative

# Warming the container at build time turns the first request after a deploy from slow into
# fast. It needs the variables production refuses to boot without — throwaway values, because
# nothing here is contacted and nothing here survives into the running container.
RUN APP_SECRET=build-only \
    DATABASE_URL=postgresql://build:build@127.0.0.1:5432/build \
    JWT_PASSPHRASE=build-only \
    php bin/console cache:warmup --no-interaction \
    && rm -rf var/cache/dev var/log/*

COPY docker-entrypoint.sh /usr/local/bin/kickoff-entrypoint
RUN chmod +x /usr/local/bin/kickoff-entrypoint

# Render routes to whatever the service listens on; the entrypoint binds Caddy to $PORT.
EXPOSE 8080

ENTRYPOINT ["kickoff-entrypoint"]
