# syntax=docker/dockerfile:1

FROM node:24-bookworm AS node

FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive

# ------------------------------------------------------------
# System packages
# ------------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        postgresql-client \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pcntl \
        bcmath \
        zip \
        intl \
        opcache \
        mbstring \
        xml \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# ------------------------------------------------------------
# Composer
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ------------------------------------------------------------
# Node.js + npm
# ------------------------------------------------------------
COPY --from=node /usr/local /usr/local

ENV PATH="/usr/local/bin:${PATH}"

# ------------------------------------------------------------
# Application user
# ------------------------------------------------------------
RUN groupadd \
        --gid "${GID}" \
        app \
    && useradd \
        --uid "${UID}" \
        --gid "${GID}" \
        --create-home \
        --shell /bin/bash \
        app

# ------------------------------------------------------------
# PHP configuration
# ------------------------------------------------------------
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# ------------------------------------------------------------
# Application
# ------------------------------------------------------------
WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
        /var/www/html/vendor \
        /var/www/html/node_modules \
    && chown -R app:app \
        /var/www/html \
        /home/app

USER app

# ------------------------------------------------------------
# Entrypoint
# ------------------------------------------------------------
ENTRYPOINT ["entrypoint.sh"]

CMD ["php-fpm"]
