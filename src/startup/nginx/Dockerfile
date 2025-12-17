FROM gemvc/nginx:1.0.0

LABEL maintainer="GEMVC Developer"

# Override APP_ENV for development (base image sets APP_ENV=production)
# FOR PRODUCTION: Comment out or delete the following line to use base image's APP_ENV=production
ENV APP_ENV=dev


WORKDIR /var/www/html

COPY . .
RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

# Set Permissions for Development (755/644 - normal web service permissions)
# FOR PRODUCTION: Comment out or delete the following two lines to use base image's secure 750/640 permissions
RUN find /var/www/html -type d -exec chmod 755 {} \;
RUN find /var/www/html -type f -exec chmod 644 {} \;