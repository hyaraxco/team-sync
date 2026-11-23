ARG TOOLCHAIN_IMAGE=provisioned
FROM node:22-bookworm-slim@sha256:e9f565d32859f0cbfb52125eafe8be39390f24dac302dc3cdf566e8ba3ab21ee AS node
FROM oven/bun:1.3.13@sha256:87416c977a612a204eb54ab9f3927023c2a3c971f4f345a01da08ea6262ae30e AS bun
FROM composer:2@sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93 AS composer
FROM php:8.4-cli-bookworm@sha256:85da896b3eeb8bcd83fe612e51a529b7d5983a1fab92ef5f63c7ac6e2842fc46 AS provisioned

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip python3 procps iproute2 \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 bcmath gd intl pdo_sqlite zip \
    && pecl install xdebug-3.5.3 \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

ENV PLAYWRIGHT_BROWSERS_PATH=/opt/browsers
WORKDIR /opt/fe
COPY team-sync-fe/package.json team-sync-fe/bun.lock ./
RUN bun install --frozen-lockfile --ignore-scripts \
    && node node_modules/playwright/cli.js install --with-deps chromium
WORKDIR /opt/be
COPY team-sync-be/composer.json team-sync-be/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-plugins \
    && python3 -c 'import json,pathlib; p=pathlib.Path("vendor"); data=json.loads((p/"composer/installed.json").read_text()); plugins=[x for package in data["packages"] for x in package.get("extra",{}).get("pest",{}).get("plugins",[])]; (p/"pest-plugins.json").write_text(json.dumps(plugins))'
COPY team-sync-be/package.json team-sync-be/bun.lock ./
RUN bun install --frozen-lockfile --ignore-scripts

FROM ${TOOLCHAIN_IMAGE} AS runtime
USER root
RUN rm -rf /source
RUN for path in app database tests bootstrap config routes resources public storage artisan; do \
        ln -sfn "/source/team-sync-be/$path" "/opt/be/$path"; \
    done
RUN rm -rf /opt/be/vendor/pestphp/pest/.temp /opt/be/vendor/pestphp/pest-plugin-mutate/.temp /opt/fe/node_modules/.vite /opt/be/node_modules/.vite-temp /opt/fe/node_modules/.vite-temp \
    && ln -s /scratch/cache/pest /opt/be/vendor/pestphp/pest/.temp \
    && ln -s /scratch/cache/pest-mutate /opt/be/vendor/pestphp/pest-plugin-mutate/.temp \
    && ln -s /scratch/cache/vite-temp-be /opt/be/node_modules/.vite-temp \
    && ln -s /scratch/cache/vite-temp-fe /opt/fe/node_modules/.vite-temp \
    && ln -s /scratch/cache/vite /opt/fe/node_modules/.vite

RUN mkdir -p /scratch /source \
    && rm -rf /tmp /var/tmp \
    && ln -s /scratch/tmp /tmp \
    && ln -s /scratch/tmp /var/tmp \
    && chmod -R a+rX /opt/browsers /opt/be /opt/fe
COPY . /source
RUN cmp /source/team-sync-be/composer.json /opt/be/composer.json \
    && cmp /source/team-sync-be/composer.lock /opt/be/composer.lock \
    && cmp /source/team-sync-be/package.json /opt/be/package.json \
    && cmp /source/team-sync-be/bun.lock /opt/be/bun.lock \
    && cmp /source/team-sync-fe/package.json /opt/fe/package.json \
    && cmp /source/team-sync-fe/bun.lock /opt/fe/bun.lock \
    && rm -rf /source/team-sync-be/vendor /source/team-sync-be/node_modules /source/team-sync-fe/node_modules /source/team-sync-fe/playwright-artifacts \
    && ln -s /opt/be/vendor /source/team-sync-be/vendor \
    && ln -s /opt/be/node_modules /source/team-sync-be/node_modules \
    && ln -s /opt/fe/node_modules /source/team-sync-fe/node_modules \
    && ln -s /scratch/storage /source/team-sync-be/storage \
    && ln -s /scratch/bootstrap-cache /source/team-sync-be/bootstrap/cache \
    && ln -s /scratch/build-be /source/team-sync-be/public/build \
    && ln -s /scratch/build-fe /source/team-sync-fe/dist \
    && ln -s /scratch/results/role-evidence /source/team-sync-fe/playwright-artifacts \
    && touch /source/team-sync-be/.env
ARG SOURCE_SHA
LABEL org.opencontainers.image.revision=$SOURCE_SHA
RUN chmod -R a+rX /source \
    && find /source -type f -exec sha256sum {} + | sort > /opt/source-sha256.txt
WORKDIR /source
USER 1000:1000
ENTRYPOINT []
CMD ["/bin/bash", "/source/scripts/sandbox/suite.sh"]
