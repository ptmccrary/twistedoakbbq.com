FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
  nginx \
  supervisor \
  git \
  curl \
  libpng-dev \
  freetype-dev \
  libjpeg-turbo-dev \
  libwebp-dev \
  icu-dev \
  libzip-dev \
  oniguruma-dev \
  libxml2-dev \
  zip \
  unzip \
  $PHPIZE_DEPS \
  linux-headers

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install \
  mysqli \
  pdo_mysql \
  mbstring \
  exif \
  pcntl \
  bcmath \
  gd \
  intl \
  zip

# Install Xdebug via PECL
RUN pecl install xdebug \
  && docker-php-ext-enable xdebug

# Set PHP configuration values
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/uploads.ini && \
  echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/uploads.ini && \
  echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini && \
  echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# CLI-only settings (errors to stderr so WP-CLI output stays clean)
COPY docker/php-cli.ini /usr/local/etc/php/php-cli.ini

# Configure nginx
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Configure Supervisor (to manage multiple processes)
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install WP-CLI
RUN curl -o /usr/local/bin/wp -sSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
  && chmod +x /usr/local/bin/wp

# Install Composer (dev tooling only: PHPUnit, PHPStan)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy startup script
COPY docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# Expose port 80
EXPOSE 80

# Use startup script as entrypoint
CMD ["/usr/local/bin/startup.sh"]
