ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm-alpine

ARG APP_UID=1000
ARG APP_GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/home/laravel/.composer/vendor/bin:${PATH}"

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu \
    libpq \
    libzip \
    nodejs \
    npm \
    postgresql-client \
    unzip \
    zip \
    && apk add --no-cache --virtual .build-deps \
    ${PHPIZE_DEPS} \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-cache .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g "${APP_GID}" -S laravel \
    && adduser -u "${APP_UID}" -S -D -G laravel laravel

WORKDIR /var/www/html

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint

USER laravel

ENTRYPOINT ["sh", "/usr/local/bin/entrypoint"]
CMD ["php-fpm", "-F"]
