FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libonig-dev libzip-dev zip curl libpq-dev \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql zip

# Enable Apache rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy project
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Use prod environment
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV SYMFONY_DOTENV_VARS=0

# Install dependencies WITHOUT scripts (avoid DB errors)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Clear & warm up cache for prod
RUN php bin/console cache:clear --env=prod || true
RUN php bin/console cache:warmup --env=prod || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Set Apache document root to /public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80