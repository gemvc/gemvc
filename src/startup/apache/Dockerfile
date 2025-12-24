# ============================================================================
# GEMVC Application Dockerfile
# Extends the base Apache image with application code
# ============================================================================

# Use the GEMVC Apache base image
FROM gemvc/apache:latest

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better caching)
COPY composer.json  ./

# Install dependencies (if composer.lock exists)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Expose port
EXPOSE 80

# Health check (wget is available in base image)
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/ || exit 1

# Start PHP-FPM and Apache (matches base image)
CMD ["/bin/bash", "-c", "php-fpm -D && httpd -D FOREGROUND"]