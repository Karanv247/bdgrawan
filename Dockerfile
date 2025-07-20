# Use official PHP image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy bot files
COPY . .

# Create required JSON files with proper permissions
RUN touch users.json errors.json coupons.json && \
    chmod 666 users.json errors.json coupons.json && \
    echo '{"redemptions":0,"active":true}' > coupons.json

# Install any PHP dependencies (if you had composer.json)
# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# RUN composer install --no-dev

# Expose port 80
EXPOSE 80

# Health check (optional)
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost/ || exit 1

# Command to run the bot
CMD ["sh", "-c", "php -S 0.0.0.0:80 & php bot.php"]
