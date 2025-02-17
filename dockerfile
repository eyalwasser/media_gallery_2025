FROM php:8.3-fpm

WORKDIR /var/www/html

# Update and install required packages including the MySQL client
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    zlib1g-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    zip \
    curl \
    unzip \
    gnupg \
    default-mysql-client \
    openconnect \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure GD with JPEG and WEBP support before installing
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mysqli zip bcmath

# Installation of Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install and enable APCu
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# Install and enable uploadprogress
RUN pecl install uploadprogress \
    && docker-php-ext-enable uploadprogress

# Enable OPcache
RUN docker-php-ext-install opcache

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# PHP Configuration
RUN echo "output_buffering = 4096" > /usr/local/etc/php/conf.d/output_buffering.ini

# Update PATH for Composer binaries
ENV PATH="/var/www/html/vendor/bin:${PATH}"

# Configure APCu
RUN echo "extension=apcu.so" > /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
    && echo "apc.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini \
    && echo "apc.shm_size=128M" >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini

# Set PHP memory limit to 512MB
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory-limit.ini


# Configure uploadprogress
RUN echo "extension=uploadprogress.so" > /usr/local/etc/php/conf.d/docker-php-ext-uploadprogress.ini

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Expose PHP-FPM port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]