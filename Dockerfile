FROM php:8.2-fpm

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions phalcon pdo_mysql mysqli zip intl gd gettext bcmath

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

RUN mkdir -p /var/www/logs/madfun && \
    chown -R www-data:www-data /var/www/logs && \
    chmod -R 775 /var/www/logs

RUN chown -R www-data:www-data /var/www/html

USER www-data