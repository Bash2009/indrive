FROM php:8.4-apache

RUN docker-php-ext-install mysqli

COPY src/ var/www/html/

EXPOSE 80