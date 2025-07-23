FROM php:7.4-apache

# Install required PHP extensions and tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    curl \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo \
        pdo_mysql \
        zip \
        intl \
        soap \
        bcmath \
        opcache

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite
RUN a2enmod headers

# Set working directory
WORKDIR /var/www/html

# Copy Apache configuration
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# PHP configuration for SuiteCRM
RUN echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/suitecrm.ini \
    && echo 'post_max_size = 100M' >> /usr/local/etc/php/conf.d/suitecrm.ini \
    && echo 'upload_max_filesize = 100M' >> /usr/local/etc/php/conf.d/suitecrm.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/suitecrm.ini \
    && echo 'max_input_time = 300' >> /usr/local/etc/php/conf.d/suitecrm.ini

EXPOSE 80

CMD ["apache2-foreground"]