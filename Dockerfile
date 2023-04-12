FROM php:8.1-fpm-alpine

WORKDIR /var/www/html/

RUN php -r "readfile('http://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer

COPY . .

RUN composer install

ENV PORT=8000

EXPOSE 8000

CMD ["php", "-S", "localhost:8000", "-t", "public"]
