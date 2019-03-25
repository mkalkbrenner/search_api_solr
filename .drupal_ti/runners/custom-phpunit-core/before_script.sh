#!/bin/bash
# @file
# Precedes drupal-ti runners/phpunit-core/before_script.sh
# Install drupal, search_api_solr, Solr and extra dependencies for travis-ci running.

set -e $DRUPAL_TI_DEBUG

echo "running .drupal_ti/runners/custom-phpunit-core/before_script.sh"

# Ensure the right Drupal version is installed.
# The first time this is run, it will install Drupal.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

# Ensure the module is linked into the code base and enabled.
drupal_ti_ensure_module

# Additional requirements for search_api_solr_tests.
# @todo Is this really needed?
cd "$DRUPAL_TI_DRUPAL_DIR"
drush en --yes drush_language composer_deploy
drush language-add de

# Enable test modules.
mv $DRUPAL_TI_DRUPAL_DIR/core/modules/system/tests/modules/entity_test \
   $DRUPAL_TI_DRUPAL_DIR/core/modules/system/entity_test
mv $DRUPAL_TI_DRUPAL_DIR/modules/contrib/search_api/tests/search_api_test_example_content \
   $DRUPAL_TI_DRUPAL_DIR/modules/contrib/search_api/search_api_test_example_content
mv $DRUPAL_TI_MODULES_PATH/$DRUPAL_TI_MODULE_NAME/tests/modules/search_api_solr_test \
   $DRUPAL_TI_MODULES_PATH/$DRUPAL_TI_MODULE_NAME/search_api_solr_test

drupal_ti_clear_caches
drush en --yes search_api_solr_test

# Install Solr.
search_api_solr_ensure_solr

# Ensure server is running for Functional tests
drupal_ti_run_server
drupal_ti_clear_caches
