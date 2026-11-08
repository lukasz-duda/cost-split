# Środowisko lokalne odwzorowujące zwykły hosting: Apache + PHP z mod_rewrite i .htaccess.
FROM php:8.5-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY app/ /var/www/html/
COPY docker/config.php /var/www/html/config.php
COPY tests/ /var/www/tests/
RUN ln -s /var/www/html /var/www/app
