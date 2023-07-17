FROM php:8.1.19-fpm-bullseye

# HOTFIX for ARM64 Architectures and VIPS (see https://github.com/opencv/opencv/issues/14884#issuecomment-706725583 for details)
ADD deployment/bash.vips-arm64-hotfix.sh /etc/bash.vips-arm64-hotfix.sh
RUN echo '. /etc/bash.vips-arm64-hotfix.sh' >>  /etc/bash.bashrc

####################### END - Dev Container only ########################

# Install intl, bcmath, pdo, pdo_mysql, mysqli, libvips
# debug tools: vim
# TODO install postgres client
#
RUN apt-get update -y && \
    apt-get install --no-install-recommends -y \
        libicu-dev \
        libxslt1-dev \
        nginx-light \
        libvips42 \
        libvips-dev \
        libffi-dev \
        procps \
        libpq-dev \
        vim \
        less \
        unzip \
        git \
        default-mysql-client \
        && \
    mkdir -p /var/log/supervisor && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-install intl bcmath pdo pdo_mysql xsl ffi && \
    pecl install vips && \
    echo "extension=vips.so" > /usr/local/etc/php/conf.d/vips.ini && \
    pecl install redis && docker-php-ext-enable redis

# for PHP 8.1, disable deprecations to temporarily make Neos/Flow work
RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/error_reporting.ini
# FFI needs to be enabled
RUN echo "ffi.enable = true" >> /usr/local/etc/php/conf.d/ffi.ini

# install composer
# IMPORTANT:
#   do not call the composer command inside the Dockerfile!
#   Otherwise, the config files will be created in root home and are not
#   readable by www-data later. (unless you chown it explicitly)
RUN curl --silent --show-error https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# application entrypoint
ADD /deployment/entrypoint.sh /
ADD /deployment/memory-limit-php.ini /usr/local/etc/php/conf.d/memory-limit-php.ini
ADD /deployment/upload-limit-php.ini /usr/local/etc/php/conf.d/upload-limit-php.ini

RUN rm -Rf /usr/local/etc/php-fpm.* && rm -Rf /etc/nginx/conf.d/*
ADD deployment/php-fpm.conf /usr/local/etc/php-fpm.conf

ADD /deployment/nginx.conf /etc/nginx/

RUN mkdir -p /var/lib/nginx /usr/local/var/log/ && \
    chown -R www-data /var/lib/nginx /usr/local/var/log/ /etc/nginx/

# cleanup & chown
RUN mkdir -p /app/Data/Persistent /app/Configuration /app/Build/Behat /composer_cache && \
    chown -R www-data /app /composer_cache /var/www && \
    apt-get clean

WORKDIR /app

USER www-data
ENTRYPOINT [ "/entrypoint.sh" ]
