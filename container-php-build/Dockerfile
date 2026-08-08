FROM php:8.5-fpm

RUN docker-php-ext-configure pcntl --enable-pcntl && docker-php-ext-install pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        opcache \
        zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY config.json /etc/myapp/config.json

WORKDIR /var/www/html
