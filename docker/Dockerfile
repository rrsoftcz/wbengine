FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
