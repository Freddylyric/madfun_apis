FROM php:8.2-fpm

# Install system dependencies and PHP extension installer
COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Removed the version 5.0.0 and mcrypt for better compatibility with PHP 8.2
RUN install-php-extensions phalcon pdo_mysql mysqli zip intl gd gettext bcmath

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

RUN mkdir -p /var/www/logs/madfun && \
    chown -R www-data:www-data /var/www/logs && \
    chmod -R 775 /var/www/logs

# Set the working directory ownership
RUN chown -R www-data:www-data /var/www/html

USER www-data