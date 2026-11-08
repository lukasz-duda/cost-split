# Środowisko lokalne odwzorowujące zwykły hosting: Apache + PHP, bez mod_rewrite.
FROM php:8.5-apache

RUN docker-php-ext-install pdo_mysql

COPY app/ /var/www/html/
COPY docker/config.php /var/www/html/config.php
COPY tests/ /var/www/tests/
RUN ln -s /var/www/html /var/www/app
