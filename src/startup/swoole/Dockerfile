# Use GEMVC base image (builds faster - extensions pre-installed)
FROM gemvc/swoole:latest AS builder

WORKDIR /var/www/html

# Copy composer files (composer.lock is optional)
COPY composer.json ./
COPY composer.lock* ./

# Install dependencies with optimizations
RUN composer install --no-dev \
    --ignore-platform-reqs \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-scripts \
    --no-interaction

# ---------------------------------------------------

# Use GEMVC base image for runtime
FROM gemvc/swoole:latest

LABEL maintainer="GEMVC Team" \
      version="5.2-alpine"

WORKDIR /var/www/html

COPY --from=builder /var/www/html/vendor ./vendor

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative

EXPOSE 9501

CMD ["php", "index.php"]
