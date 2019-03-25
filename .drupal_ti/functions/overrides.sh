#!/bin/bash

#
# Overrides drupal_ti_install_drupal.
#
function drupal_ti_install_drupal() {
  git clone --depth 1 --branch "$DRUPAL_TI_CORE_BRANCH" http://git.drupal.org/project/drupal.git
  cd drupal
  composer install

  # Update PHPUnit for 8.6 or newer.
  if [ "${DRUPAL_TI_CORE_BRANCH:2:1}" -gt "5" ]
  then
    composer run-script drupal-phpunit-upgrade
  fi

  # Add extra composer dependencies when required.
  if [ -n "$COMPOSER_EXTRA_DEPENDENCIES" ]
  then
    composer require $COMPOSER_EXTRA_DEPENDENCIES --no-interaction
  fi

  php -d sendmail_path=$(which true) ~/.composer/vendor/bin/drush --yes -v site-install "$DRUPAL_TI_INSTALL_PROFILE" --db-url="$DRUPAL_TI_DB_URL"
  drush use $(pwd)#default
}
