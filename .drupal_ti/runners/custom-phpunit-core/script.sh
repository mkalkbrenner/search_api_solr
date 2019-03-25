#!/bin/bash
# @file
# Simple script to run the core phpunit tests via travis-ci.

cd "$DRUPAL_TI_DRUPAL_DIR"

# Find absolute path to modules directory.
MODULE_DIR=$(cd "$DRUPAL_TI_MODULES_PATH"; pwd)

# Set PHPUnit arguments.
PHPUNIT_ARGS="--group search_api_solr --verbose --debug"

if [ ${SOLR_CLOUD} == "true" ]; then
  PHPUNIT_ARGS="$PHPUNIT_ARGS --exclude-group solr_no_cloud"
  echo "Solr Cloud tests: enabled"
else
  PHPUNIT_ARGS="$PHPUNIT_ARGS --exclude-group solr_cloud"
  echo "Solr Cloud tests: disabled"
fi

# Run tests.
cd core
../vendor/bin/phpunit $PHPUNIT_ARGS "$MODULE_DIR/$DRUPAL_TI_MODULE_NAME/$DRUPAL_TI_PHPUNIT_CORE_SRC_DIRECTORY"
