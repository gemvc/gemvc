# Pin major FrankenPHP + PHP line. Classic by default; worker: use Caddyfile.worker — docs/guides/frankenphp.md
FROM dunglas/frankenphp:1-php8.4-bookworm

LABEL maintainer="GEMVC Developer"

# Override APP_ENV for development
# FOR PRODUCTION: Comment out or delete the following line
ENV APP_ENV=dev
ENV SERVER_NAME=:80
# Classic mode (default). For worker mode uncomment:
# ENV FRANKENPHP_CONFIG="worker ./worker.php"
# And COPY/use Caddyfile.worker as /etc/frankenphp/Caddyfile

WORKDIR /app

RUN install-php-extensions \
	pdo_mysql \
	pdo_pgsql \
	redis \
	apcu \
	opcache \
	@composer

COPY . .
COPY Caddyfile /etc/frankenphp/Caddyfile
# Older image layouts used /etc/frankenphp/Caddyfile — current dunglas/frankenphp uses /etc/frankenphp/Caddyfile

RUN composer install --no-dev --optimize-autoloader \
	&& chown -R www-data:www-data /app

# Set Permissions for Development (755/644)
# FOR PRODUCTION: tighten as needed
RUN find /app -type d -exec chmod 755 {} \; \
	&& find /app -type f -exec chmod 644 {} \;
