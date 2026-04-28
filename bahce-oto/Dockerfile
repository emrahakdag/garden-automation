FROM php:8.2-apache

# PHP eklentileri
RUN docker-php-ext-install pdo pdo_mysql

# Apache .htaccess ve mod重写 etkin
RUN a2enmod rewrite

# Document root'i public/ dizinine ayarla
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-enabled/000-default.conf

# Upload limiti (sensör veri paketleri için yeterli)
RUN echo "upload_max_filesize=10M\npost_max_size=10M" > /usr/local/etc/php/conf.d/uploads.ini

COPY src/ /var/www/html/

# Çalıştırma izni
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
