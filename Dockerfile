# ----------------------------------------------------------------------------
# Dockerfile - PHP + Apache image for deployment on Render.
# Render does not natively run PHP, so we package the app in a Docker image
# based on the official php:8.2-apache image with pdo_mysql enabled.
# ----------------------------------------------------------------------------
FROM php:8.2-apache

# Install the PDO MySQL driver
RUN docker-php-ext-install pdo pdo_mysql

# Apache should listen on the port Render injects (default 10000)
ENV PORT=10000
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy the application into the webroot
COPY . /var/www/html/

# Default landing page is customers.php
RUN echo "DirectoryIndex customers.php index.php index.html" > /etc/apache2/conf-available/directoryindex.conf \
    && a2enconf directoryindex

EXPOSE 10000
CMD ["apache2-foreground"]
