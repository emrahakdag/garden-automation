FROM php:8.2-apache

# PHP eklentileri
RUN docker-php-ext-install pdo pdo_mysql

# Apache mod_rewrite
RUN a2enmod rewrite

# Document root tüm proje dizini (src/api/ ve public/ erişilebilir olmalı)
# Varsayılan Apache ayarını geri getir
RUN sed -i 's|/var/www/html/public|/var/www/html|g' /etc/apache2/sites-enabled/000-default.conf

# Upload limiti
RUN echo "upload_max_filesize=10M\npost_max_size=10M" > /usr/local/etc/php/conf.d/uploads.ini

# Proje dosyalarını kopyala
COPY bahce-oto/ /var/www/html/

# API erişimini API klasöründen yapabilmek için
# src/api/ dosyalarını public/api/ altına da koy (rewrite ile erişim)
RUN if [ -d src/api ] && [ ! -d public/api ]; then
    mkdir -p public/api && cp src/api/*.php public/api/;
fi

# src dizinine doğrudan URL ile erişimi engelle
RUN echo '<Directory /var/www/html/src>' > /etc/apache2/conf-available/no-src.conf && \
    echo "    Require all denied" >> /etc/apache2/conf-available/no-src.conf && \
    echo '</Directory>' >> /etc/apache2/conf-available/no-src.conf && \
    a2enconf no-src && \
    chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
