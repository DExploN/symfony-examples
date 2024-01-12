ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-fpm

# install all the dependencies and enable PHP modules
RUN set -e; \
  apt-get update && apt-get upgrade -y \
    && apt-get install -y \
      git\
      procps \
      unzip \
      libicu-dev \
      zlib1g-dev \
      libxml2 \
      libxml2-dev \
      libreadline-dev \
      libzip-dev \
      librabbitmq-dev \
      libfreetype6-dev \
      libjpeg62-turbo-dev \
      libpng-dev \
      libwebp-dev \
    && pecl install amqp-1.11.0 \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && yes '' | pecl install -o -f redis && docker-php-ext-enable redis \
    && docker-php-ext-install \
      pdo_mysql \
      intl \
      opcache \
      zip \
      sockets\
      gd \
      pcntl \
    && docker-php-ext-enable amqp

RUN pecl install install -f xdebug-3.1.3 \
        && docker-php-ext-enable xdebug
COPY ./ini_dir $PHP_INI_DIR

# install kafka
RUN apt-get install -y librdkafka-dev \
        && pecl install -f rdkafka-6.0.3 \
        && docker-php-ext-enable rdkafka

# remove tmp
RUN  rm -rf /tmp/* \
        && rm -rf /var/list/apt/* \
        && rm -rf /var/lib/apt/lists/* \
        && apt-get clean

COPY --from=composer /usr/bin/composer /usr/bin/composer

ARG UID
ARG USER_NAME

RUN useradd -m -u ${UID} -g root ${USER_NAME}