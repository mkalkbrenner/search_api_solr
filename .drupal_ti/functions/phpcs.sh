#!/usr/bin/env bash
# @file
# Common functionality for installing phpcs and friends.

#
# Ensures that phpcs is installed.
#
function search_api_solr_ensure_phpcs() {
    # This function is re-entrant.
    if [ -r "$TRAVIS_BUILD_DIR/../search_api_solr-phpcs-installed" ]
    then
        return
    fi

    # Check if drupal/coder is already available.
    DRUPAL_CODER=$(composer info --no-interaction drupal/coder || echo "")

    if [ -z "$DRUPAL_CODER" ]
    then
        # Install drupal/coder globally.
        echo "Installing drupal/coder: $SEARCH_API_SOLR_DRUPAL_CODER_VERSION"
        composer global require --no-interaction "$SEARCH_API_SOLR_DRUPAL_CODER_VERSION"
    else
        echo "phpcs $SEARCH_API_SOLR_DRUPAL_CODER_VERSION is already installed."
        composer global install --no-interaction
    fi

    # Install Drupal and DrupalPractice coding standards.
    phpcs --config-set installed_paths $HOME/.composer/vendor/drupal/coder/coder_sniffer

    touch "$TRAVIS_BUILD_DIR/../search_api_solr-phpcs-installed"
}
