FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    zip \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    mbstring \
    xml \
    curl

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Create necessary directories
RUN mkdir -p uploads logs temp

# Set proper permissions
RUN chmod -R 755 /app \
    && chmod -R 777 uploads logs temp

# Expose port
EXPOSE 8080

# Start PHP built-in server with router
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app", "router.php"]