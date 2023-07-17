#!/bin/bash
set -ex

# Hotfix for M1
source /etc/bash.vips-arm64-hotfix.sh

mkdir -p /var/www/.composer || true
composer config --global cache-dir /composer_cache
#composer config --global 'preferred-install.sandstorm/*' source

cd /app/Build/Behat
composer install
cd /app
composer install

./flow doctrine:migrate

./flow resource:publish
./flow flow:cache:flush
./flow cache:warmup

# e2e test
./flow behat:setup
rm bin/selenium-server.jar # we do not need this

# start nginx in background
nginx &

# start PHP-FPM
exec /usr/local/sbin/php-fpm
