FROM php:8.1-apache

RUN a2enmod rewrite

COPY . /var/www/html/

RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

EXPOSE 80