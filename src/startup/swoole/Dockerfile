# Use GEMVC base image (builds faster - extensions pre-installed)
FROM gemvc/swoole:latest AS builder

WORKDIR /var/www/html

COPY composer.json composer.lock ./

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

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:9501/ || exit 1

CMD ["php", "index.php"]
