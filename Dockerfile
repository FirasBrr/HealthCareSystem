FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libonig-dev libzip-dev zip curl libpq-dev \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql zip

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Environment
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Install Symfony dependencies (skip scripts to avoid DB crash)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# ✅ FULL Apache config (NO .htaccess needed anymore)
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
\n\
    <Directory /var/www/html/public>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride None\n\
        Require all granted\n\
    </Directory>\n\
\n\
    RewriteEngine On\n\
    RewriteCond %%{REQUEST_FILENAME} !-f\n\
    RewriteRule ^ index.php [QSA,L]\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# Expose port
EXPOSE 80