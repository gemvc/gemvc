FROM gemvc/nginx:1.0.0

LABEL maintainer="GEMVC Developer"

WORKDIR /var/www/html

COPY . .
RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html