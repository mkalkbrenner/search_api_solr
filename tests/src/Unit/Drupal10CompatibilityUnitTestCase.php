<?php

namespace Drupal\Tests\search_api_solr\Unit;

use Drupal\Tests\UnitTestCase;

if (class_exists('\Prophecy\PhpUnit\ProphecyTrait')) {
  abstract class Drupal10CompatibilityUnitTestCase extends UnitTestCase {
    use \Prophecy\PhpUnit\ProphecyTrait;
  }
}
else {
  abstract class Drupal10CompatibilityUnitTestCase extends UnitTestCase {}
}
