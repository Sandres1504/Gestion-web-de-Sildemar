FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y libzip-dev unzip default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
