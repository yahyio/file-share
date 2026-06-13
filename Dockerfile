FROM php:8.2-apache

WORKDIR /var/www/html

COPY . .

RUN mkdir -p uploads && chmod 777 uploads \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && printf "upload_max_filesize=25M\npost_max_size=26M\nmemory_limit=64M\n" \
       > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80
