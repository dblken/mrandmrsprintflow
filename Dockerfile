FROM php:8.2-apache

# =========================
# SYSTEM DEPENDENCIES
# =========================
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mbstring zip

# =========================
# 🔥 FIX APACHE MPM (CRITICAL)
# =========================
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2enmod mpm_prefork

# Enable required Apache modules
RUN a2enmod rewrite

# =========================
# 🔥 FIX RAILWAY PORT (IMPORTANT FIX)
# =========================
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf \
    && sed -i "s/80/\${PORT}/g" /etc/apache2/ports.conf

# =========================
# APP FILES
# =========================
COPY . /var/www/html/
WORKDIR /var/www/html/

# =========================
# COMPOSER
# =========================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# =========================
# PERMISSIONS
# =========================
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80