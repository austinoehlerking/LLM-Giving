FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev && docker-php-ext-configure gd --with-jpeg && docker-php-ext-install pdo_mysql gd && rm -rf /var/lib/apt/lists/* && a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
RUN sed -ri 's!/var/www/html!/var/www/app/public!g' /etc/apache2/sites-available/*.conf && printf '<Directory /var/www/app/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/llmgiving.conf && a2enconf llmgiving
WORKDIR /var/www/app
COPY bin/ bin/
COPY content/ content/
COPY migrations/ migrations/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY LICENSE ./LICENSE
RUN mkdir -p storage && chown -R www-data:www-data storage && printf 'upload_max_filesize=10M\npost_max_size=32M\ndisplay_errors=Off\n' > /usr/local/etc/php/conf.d/llmgiving.ini
