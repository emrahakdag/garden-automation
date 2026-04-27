FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# App files
RUN mkdir -p /app
COPY . /app

RUN chown -R www-data:www-data /app

# Supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 3000

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
