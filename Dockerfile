FROM php:8.2-apache

# Install needed PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (important for PHP apps)
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Railway requires PORT env
ENV PORT=80
EXPOSE 80