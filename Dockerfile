FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/server-name.conf \
    && a2enconf server-name

WORKDIR /var/www/html

COPY . /var/www/html/
COPY docker/start-apache.sh /usr/local/bin/start-apache

RUN mkdir -p uploads/slides uploads/resources uploads/student_photos \
    && chown -R www-data:www-data uploads \
    && find uploads -type d -exec chmod 775 {} \;

CMD ["sh", "/usr/local/bin/start-apache"]
