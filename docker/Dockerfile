FROM php:7.3-cli-alpine

# SYS: Install required packages
RUN apk --no-cache upgrade && \
    apk add autoconf bash gcc g++ git make shadow sudo

# PHP: Install php extensions
RUN docker-php-ext-install opcache posix pcntl > /dev/null 2>&1 && \
    docker-php-ext-enable opcache posix pcntl > /dev/null 2>&1

WORKDIR /var/www/html

ENTRYPOINT ["tail", "-f", "/dev/null"]
