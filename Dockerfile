FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    wget \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql intl gd exif opcache zip

RUN wget https://get.symfony.com/cli/installer -O - | bash && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

CMD ["php", "-S", "0.0.0.0:80", "-t", "public"]