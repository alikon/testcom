# Start from the official PHP 8.4 image with Apache
FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y \
    git \
    zstd \
    wget \
    curl \
    sudo \
    unzip \
    # PHP extensions dependencies
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    # MySQL client (il server MySQL gira nel container dedicato)
    default-mysql-client \
    # PostgreSQL client (il server PostgreSQL gira nel container dedicato)
    postgresql-client \
    libpq-dev \
    # Cypress dependencies
    xvfb \
    libgtk2.0-0 \
    libgtk-3-0 \
    libgbm-dev \
    libnotify-dev \
    libnss3 \
    libxss1 \
    libasound2 \
    libxtst6 \
    xauth \
    && \
    # PHP extensions
    docker-php-ext-install zip mysqli pdo pdo_mysql pdo_pgsql pgsql && \
    # Node.js LTS
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y nodejs && \
    # Composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    # Xdebug
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    # Apache
    a2enmod rewrite ssl && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    # Cleanup
    apt-get clean && rm -rf /var/lib/apt/lists/* && \
    # PHP config
    mkdir -p /var/log/ && \
    touch /var/log/php_errors.log && \
    chown -R www-data:www-data /var/log/ && \
    chmod 766 /var/log/php_errors.log && \
    echo "upload_tmp_dir = /tmp"           > /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "post_max_size = 64M"            >> /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "upload_max_filesize = 64M"      >> /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "log_errors = On"                >> /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/custom-php.ini && \
    echo "error_reporting = E_ALL"        >> /usr/local/etc/php/conf.d/custom-php.ini

# Apache VirtualHost per Joomla
COPY apache-joomla.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80 443
CMD ["apache2-foreground"]