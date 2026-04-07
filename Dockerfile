FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libonig-dev libzip-dev zip curl libpq-dev \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql zip

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . .

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV APP_ENV=prod
ENV APP_DEBUG=0

RUN composer install --no-dev --optimize-autoloader

RUN php bin/console cache:clear --env=prod || true
RUN php bin/console cache:warmup --env=prod || true

RUN chown -R www-data:www-data /var/www/html

# ✅ IMPORTANT FIXES
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

RUN echo '<Directory /var/www/html/public>
    AllowOverride All
</Directory>' >> /etc/apache2/apache2.conf

EXPOSE 80