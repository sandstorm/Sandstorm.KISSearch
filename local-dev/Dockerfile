FROM php:8.2-cli

RUN apt-get update \
    # install GraphicsMagick
	&& apt-get install -y \
		libgraphicsmagick1-dev \
        graphicsmagick \
        zlib1g-dev \
        libicu-dev \
        libpq-dev \
        unzip \
        git \
        gcc g++ \
        --no-install-recommends \
	&& pecl -vvv install gmagick-beta && docker-php-ext-enable gmagick \
    # DB drivers
    && docker-php-ext-install pdo_mysql pdo_pgsql \
    # redis
    && pecl install redis && docker-php-ext-enable redis \
	# intl
	&& docker-php-ext-configure intl && docker-php-ext-install intl \
    # cleanup
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# install composer
# IMPORTANT:
#   do not call the composer command inside the Dockerfile!
#   Otherwise, the config files will be created in root home and are not
#   readable by www-data later. (unless you chown it explicitly)
RUN curl --silent --show-error https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

WORKDIR /app
EXPOSE 8081

# copy everything in the project into the container. This is what
# makes this image so fast!
COPY ./local-dev-app /app

COPY ./local-dev/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# entrypoint script sets up the Neos instance (composer install, doctrine migrate, etc...)
ENTRYPOINT /entrypoint.sh
