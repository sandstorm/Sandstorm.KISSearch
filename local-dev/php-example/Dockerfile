FROM php:8.2-cli

RUN apt-get update \
    # install GraphicsMagick
	&& apt-get install -y \
        zlib1g-dev \
        libicu-dev \
        libpq-dev \
        unzip \
        git \
        gcc g++ \
        --no-install-recommends \
    # DB drivers
    && docker-php-ext-install mysqli pgsql \
	# intl
	&& docker-php-ext-configure intl && docker-php-ext-install intl \
    # cleanup
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app
EXPOSE 8082

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# entrypoint starts simple php server
ENTRYPOINT /entrypoint.sh
