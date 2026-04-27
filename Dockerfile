# Arduino Monitor - Coolify Dockerfile
# PHP 8.2 + Apache ile optimize edilmiş

FROM php:8.2-apache

# Sistem güncellemeleri ve gerekli paketler
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install pdo pdo_mysql zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apache modüllerini etkinleştir
RUN a2enmod rewrite

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Dosyaları kopyala
COPY . /var/www/html/

# Gerekli dizin izinlerini ayarla
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# PHP ayarlarını optimize et
RUN echo 'upload_max_filesize = 10M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 10M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_execution_time = 30' >> /usr/local/etc/php/conf.d/uploads.ini

# Apache sanal sunucu yapılandırması
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Varsayılan Apache portunu 80 olarak bırak (Coolify yönlendirecek)

EXPOSE 80

CMD ["apache2-foreground"]
