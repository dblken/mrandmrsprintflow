FROM php:8.2-apache

# Install dependencies for GD and zip, plus basic utilities
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mbstring zip

# Enable Apache rewrite (optional but useful)
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
# Using --no-dev and --no-scripts to prevent memory or script execution issues during Railway build
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Fix permissions
RUN chown -R www-data:www-data /var/www/html
