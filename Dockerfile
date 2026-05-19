FROM php:8.2-apache

# Enable mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Install ffmpeg (audio conversion) and yt-dlp (YouTube download)
RUN apt-get update && apt-get install -y ffmpeg python3 python3-pip \
    && pip3 install --break-system-packages yt-dlp \
    && rm -rf /var/lib/apt/lists/*

# Copy project files into web root
COPY . /var/www/html/

# Ensure correct permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
