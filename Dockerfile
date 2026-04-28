FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

COPY . /var/www/html/

# Fix Apache MPM conflict
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork || true

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80