# Pin major FrankenPHP + PHP line (classic mode). See docs/guides/frankenphp.md
FROM dunglas/frankenphp:1-php8.3-bookworm

LABEL maintainer="GEMVC Developer"

# Override APP_ENV for development
# FOR PRODUCTION: Comment out or delete the following line
ENV APP_ENV=dev
ENV SERVER_NAME=:80

WORKDIR /app

RUN install-php-extensions \
	pdo_mysql \
	pdo_pgsql \
	redis \
	apcu \
	opcache \
	@composer

COPY . .
COPY Caddyfile /etc/caddy/Caddyfile

RUN composer install --no-dev --optimize-autoloader \
	&& chown -R www-data:www-data /app

# Set Permissions for Development (755/644)
# FOR PRODUCTION: tighten as needed
RUN find /app -type d -exec chmod 755 {} \; \
	&& find /app -type f -exec chmod 644 {} \;
