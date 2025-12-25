# ============================================================================
# GEMVC Application Dockerfile
# Extends the base GEMVC Apache image with application code
# ============================================================================

FROM gemvc/apache:latest

WORKDIR /var/www/html

COPY composer.json composer.lock* ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer clear-cache

COPY . .

RUN chown -R apache:apache /var/www/html \
    && chmod -R 755 /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# No need to override CMD, EXPOSE, or HEALTHCHECK - base image handles it
