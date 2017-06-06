FROM composer/composer:php5


RUN apt-get update && \
  apt-get install -y php5-intl libicu-dev default-jre libmemcached-dev && \
  rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install xml
RUN docker-php-ext-enable xml

RUN docker-php-ext-install opcache
RUN docker-php-ext-enable opcache

RUN docker-php-ext-install session
RUN docker-php-ext-enable session

RUN docker-php-ext-install mbstring
RUN docker-php-ext-enable mbstring

RUN docker-php-ext-install pdo
RUN docker-php-ext-enable pdo

RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-enable pdo_mysql

RUN docker-php-ext-install pcntl
RUN docker-php-ext-enable pcntl

RUN docker-php-ext-install gettext
RUN docker-php-ext-enable gettext

RUN docker-php-ext-install bcmath
RUN docker-php-ext-enable bcmath

RUN docker-php-ext-install sysvsem
RUN docker-php-ext-enable sysvsem

RUN docker-php-ext-install sockets
RUN docker-php-ext-enable sockets

RUN pecl install intl
RUN docker-php-ext-install intl
RUN docker-php-ext-enable intl

RUN pecl install memcached
RUN echo extension=memcached.so >> /usr/local/etc/php/conf.d/memcached.ini

RUN touch /usr/local/etc/php/conf.d/999-custom.ini
RUN echo date.timezone="Europe/Kiev" >> /usr/local/etc/php/conf.d/999-custom.ini
RUN echo phar.readonly=0 >> /usr/local/etc/php/conf.d/999-custom.ini

WORKDIR /var/www

COPY composer.json composer.lock ./

#ADD .ssh/id_rsa /root/.ssh/id_rsa
#RUN chmod 700 /root/.ssh/id_rsa
#RUN echo "Host github.com\n\tStrictHostKeyChecking no\n" >> /root/.ssh/config
#RUN echo "Host git.webmaniacs.net\n\tStrictHostKeyChecking no\n" >> /root/.ssh/config

RUN composer install --prefer-source --no-interaction

ENTRYPOINT /bin/bash
CMD ["true"]