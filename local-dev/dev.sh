#!/bin/bash
############################## DEV_SCRIPT_MARKER ##############################
# This script is used to document and run recurring tasks in development.     #
#                                                                             #
# You can run your tasks using the script `./dev some-task`.                  #
# You can install the Sandstorm Dev Script Runner and run your tasks from any #
# nested folder using `dev some-task`.                                        #
# https://github.com/sandstorm/Sandstorm.DevScriptRunner                      #
###############################################################################

set -e

######### TASKS #########

composer-install() {
  _dc_neos "composer install"
}

db-setup() {
  _dc_neos "./flow doctrine:migrate"
  _dc_neos "./flow cr:setup --content-repository default"
}

create-user() {
  _dc_neos "./flow user:create admin password --roles Administrator"
}

create-dev-sites() {
  _dc_neos "./flow site:create dev-site Dev.Site Dev.Site:Document.Homepage"
  _dc_neos "./flow domain:add --scheme=http --port:8081 dev-site dev-site.local"
}

import-demo() {
  _dc_neos "./flow site:importall --package-key Neos.Demo"
  _dc_neos "./flow domain:add --scheme=http --port:8081 dev-site dev-site.local"
}

resource-publish() {
  _dc_neos "./flow resource:publish"
}

cache-warmup() {
  _dc_neos "./flow flow:cache:flush"
  _dc_neos "./flow cache:warmup"
}

setup-running-container() {
  composer-install
  db-setup
  create-user
  create-sites
  import-demo
  resource-publish
  cache-warmup
}

build-container() {
  cd app
  rm -rf Data/Logs || true
  rm -rf Data/Temporary || true
  composer install --ignore-platform-reqs
  cd ..
  docker compose build
}

kickstart-site() {
  _dc_neos "./flow kickstart:site --package-key Dev.Site || true"
}

####### Utilities #######

_dc_neos() {
  docker compose exec neos bash -c "${1}"
}

_log_success() {
  printf "\033[0;32m%s\033[0m\n" "${1}"
}
_log_warning() {
  printf "\033[1;33m%s\033[0m\n" "${1}"
}
_log_error() {
  printf "\033[0;31m%s\033[0m\n" "${1}"
}

"$@"

