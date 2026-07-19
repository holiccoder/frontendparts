FROM php:8.4-apache

ARG WWWUSER=1000
ARG WWWGROUP=1000

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get install -y \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        libssl-dev \
        unzip \
        git \
        curl \
        nano \
        libpq-dev \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        opcache \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Enable Apache rewrite module and set document root
RUN a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 24
COPY --from=node:24-slim /usr/local/bin /usr/local/bin
COPY --from=node:24-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node:24-slim /usr/local/include/node /usr/local/include/node

# Match the host user/group so mounted files have correct ownership
RUN groupadd --force -g ${WWWGROUP} sail \
    && useradd -ms /bin/bash --no-user-group -u ${WWWUSER} -g ${WWWGROUP} sail \
    || true

WORKDIR /var/www/html

EXPOSE 80
