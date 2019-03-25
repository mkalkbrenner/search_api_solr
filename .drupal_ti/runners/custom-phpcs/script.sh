#!/usr/bin/env bash
# @file
# phpcs integration - Script step.

set -e $DRUPAL_TI_DEBUG

echo "running .drupal_ti/runners/custom-phpcs/script.sh"

if [ -n "$SEARCH_API_SOLR_PHPCS_ARGS" ]; then
  ARGS=( $SEARCH_API_SOLR_PHPCS_ARGS )
else
  ARGS=(
    "--colors"
    "--standard=Drupal,DrupalPractice"
    "--extensions=php,module,inc,install,test,profile,theme,css,info,txt,md"
    "--ignore=node_modules,bower_components,vendor"
    "${TRAVIS_BUILD_DIR}"
  )
fi

# Show sniffs.
$HOME/.composer/vendor/bin/phpcs "${ARGS[@]}" -e

# Run.
$HOME/.composer/vendor/bin/phpcs "${ARGS[@]}"
