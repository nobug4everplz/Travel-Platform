FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install system deps for PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql
RUN docker-php-ext-install opcache

# Copy app
COPY . /var/www/html/
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Set document root to public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Create cache/uploads dirs
RUN mkdir -p /var/www/html/cache /var/www/html/uploads/photos && chmod 777 /var/www/html/cache /var/www/html/uploads /var/www/html/uploads/photos

# Symlink uploads into public/ so Apache (doc root = public/) can serve static files
RUN ln -sf /var/www/html/uploads /var/www/html/public/uploads

# Startup script
RUN echo '#!/bin/sh\nphp /var/www/html/init-db.php\nexec apache2-foreground' > /start.sh && chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
