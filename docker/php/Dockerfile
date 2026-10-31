FROM php:8.5.12-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    libsqlite3-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_sqlite zip intl pcntl

RUN pecl install pcov && docker-php-ext-enable pcov

ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_PROCESS_TIMEOUT=2000
COPY --from=composer:2.11.0 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/php.ini

RUN groupadd -g 1000 appuser \
    && useradd -u 1000 -g 1000 -m appuser \
    && mkdir -p /var/www/html \
    && chown -R appuser:appuser /var/www/html

RUN sed -i 's/www-data/appuser/g' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
USER 1000
