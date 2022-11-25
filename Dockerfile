FROM php:8.1-alpine3.16

RUN apk add --virtual .build-deps --no-cache --update autoconf file g++ gcc libc-dev make pkgconf re2c zlib-dev bash git && \
    apk add --repository http://dl-cdn.alpinelinux.org/alpine/edge/community/ --allow-untrusted gnu-libiconv && \
    pecl install apcu redis xdebug && \
    docker-php-ext-install pcntl && \
    docker-php-ext-enable apcu redis xdebug && \
    apk del -f .build-deps && \
    pecl clear cache

ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www