FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

# System tools + PHP extensions, using only official Docker Hub images (no third-party installer image).
# New extensions / system libs are added HERE, then: docker compose up --build
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip curl postgresql-client libpq-dev libzip-dev libicu-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql pcntl bcmath zip intl opcache \
 && yes '' | pecl install redis \
 && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as a non-root user matching the host user so files created in the bind mount are yours
RUN groupadd -g ${GID} app \
 && useradd -m -u ${UID} -g ${GID} -s /bin/bash app \
 && mkdir -p /var/www/html/vendor \
 && chown -R app:app /var/www/html

WORKDIR /var/www/html

COPY docker/php/local.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV COMPOSER_HOME=/home/app/.composer \
    COMPOSER_MEMORY_LIMIT=-1

USER app

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
