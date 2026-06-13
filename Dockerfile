FROM php:8.2-apache

WORKDIR /var/www/html

COPY . .

RUN mkdir -p uploads && chmod 777 uploads \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80
