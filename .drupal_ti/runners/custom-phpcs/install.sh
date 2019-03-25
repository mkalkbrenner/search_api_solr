#!/usr/bin/env bash
# Simple script to install dependencies for travis-ci running.

set -e $DRUPAL_TI_DEBUG

echo "running .drupal_ti/runners/custom-phpcs/install.sh"

# Ensure that phpcs is installed.
search_api_solr_ensure_phpcs
