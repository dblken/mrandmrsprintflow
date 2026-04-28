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
# 🔥 FORCE SINGLE MPM (REAL FIX)
# =========================
RUN a2dismod mpm_event || true \
 && a2dismod mpm_worker || true

# HARD REMOVE auto-enabled MPM config
RUN rm -f /etc/apache2/mods-enabled/mpm_*

# FORCE ONLY PREFORK
RUN echo "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so" > /etc/apache2/mods-enabled/mpm_prefork.load
RUN echo "<IfModule mpm_prefork_module>\nStartServers 2\nMinSpareServers 2\nMaxSpareServers 5\nMaxRequestWorkers 150\nMaxConnectionsPerChild 3000\n</IfModule>" > /etc/apache2/mods-enabled/mpm_prefork.conf

# Enable required Apache modules
RUN a2enmod rewrite

# VERY IMPORTANT: Test configuration to catch conflict early
RUN apachectl configtest

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