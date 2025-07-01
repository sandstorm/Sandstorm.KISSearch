#!/bin/bash

set -ex

_log_green() {
  printf "\033[0;32m%s\033[0m\n" "${1}"
}

######################
### setup composer ###

_log_green "### 1. setup composer"
mkdir -p /var/www/.composer || true
composer config --global cache-dir /composer_cache
#composer config --global 'preferred-install.sandstorm/*' source

######################
### setup application ###

_log_green "### 2. setup application"
cd /app

composer update

if [ ! -f ./flow ]; then
  _log_green " - flow is not installed, running 'composer install'"
  composer install
fi

_log_green "### 3. starting flow dev server"
./flow server:run --host 0.0.0.0

