#!/bin/bash

#
# Ensures Solr is installed.
#
function search_api_solr_ensure_solr() {
  # This function is re-entrant.
  if [ -r "$TRAVIS_BUILD_DIR/../search_api_solr-solr-installed" ]
  then
    return
  fi

  # Generate a multilingual Solr configuration.
  cd "$DRUPAL_TI_DRUPAL_DIR"
  drush solr-gsc solr_search_server conf.zip $SOLR_VERSION

  # Download and extract Solr.
  wget -nc --continue -v --tries=3 --directory-prefix=$TRAVIS_BUILD_DIR/solr_downloads "http://archive.apache.org/dist/lucene/solr/${SOLR_VERSION}/solr-${SOLR_VERSION}.tgz"
  tar -xzf $TRAVIS_BUILD_DIR/solr_downloads/solr-${SOLR_VERSION}.tgz -C $TRAVIS_BUILD_DIR

  # Setup Solr conf.
  mkdir -p $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/d8/conf
  unzip -o conf.zip -d $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/d8/conf
  echo "solr.install.dir=$TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}" >> $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/d8/conf/solrcore.properties
  ls -lh $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/d8/conf

  # Start Solr and create collections.
  if [ ${SOLR_CLOUD} == "true" ]; then
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr start -e cloud -noprompt || travis_terminate 1;
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr delete -c gettingstarted || travis_terminate 1;
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr create -c techproducts -s 2 -rf 2 -d $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/configsets/sample_techproducts_configs/conf -n sample_techproducts_configs || travis_terminate 1;
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/post -c techproducts $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/example/exampledocs/*.xml || travis_terminate 1;
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr create -c d8 -s 2 -rf 2 -d $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/d8/conf -n d8 || travis_terminate 1;
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr create -c checkpoints -s 1 -rf 2 || travis_terminate 1;
  else
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr start -e techproducts || travis_terminate 1;
    $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/bin/solr create -c d8 -d $TRAVIS_BUILD_DIR/solr-${SOLR_VERSION}/server/solr/d8/conf || travis_terminate 1;
  fi

  touch "$TRAVIS_BUILD_DIR/../search_api_solr-solr-installed"
}
