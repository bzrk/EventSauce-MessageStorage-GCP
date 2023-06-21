FROM php:8.1-cli-bullseye

RUN apt -y update && \
    apt install -y git libzip-dev zip

RUN pecl install grpc-1.48.1 && \
    docker-php-ext-enable grpc

COPY --from=composer /usr/bin/composer /usr/bin/composer