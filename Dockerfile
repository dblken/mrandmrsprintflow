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

# Forcefully disable event and worker MPMs by deleting their configuration files
RUN rm -f /etc/apache2/mods-available/mpm_event.load \
    /etc/apache2/mods-available/mpm_worker.load \
    /etc/apache2/mods-enabled/mpm_event.load \
    /etc/apache2/mods-enabled/mpm_worker.load \
    /etc/apache2/mods-available/mpm_event.conf \
    /etc/apache2/mods-available/mpm_worker.conf \
    /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.conf

# Ensure prefork and rewrite are enabled
RUN a2enmod mpm_prefork rewrite

# Make Apache use the PORT environment variable provided by Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Fix permissions
RUN chown -R www-data:www-data /var/www/html
