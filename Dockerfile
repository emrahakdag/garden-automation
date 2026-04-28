FROM php:8.2-apache

# PHP eklentileri
RUN docker-php-ext-install pdo pdo_mysql

# Apache mod_rewrite
RUN a2enmod rewrite

# Document root'i public/ dizinine ayarla
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-enabled/000-default.conf

# Upload limiti
RUN echo "upload_max_filesize=10M\npost_max_size=10M" > /usr/local/etc/php/conf.d/uploads.ini

COPY bahce-oto/ /var/www/html/

# Çalıştırma izni
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
