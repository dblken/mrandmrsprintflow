FROM php:8.2-apache

# Install GD dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd \
    && docker-php-ext-install gd

# Enable Apache rewrite (optional but useful)
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader

# Fix permissions
RUN chown -R www-data:www-data /var/www/html
