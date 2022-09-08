FROM php:8.1-cli

RUN apt -y update && \
    apt install -y git libzip-dev zip

RUN pecl install grpc-1.48.1 && \
    docker-php-ext-enable grpc

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"