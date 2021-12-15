<?php

namespace Drupal\Tests\search_api_solr_autocomplete\Kernel;

use Drupal\search_api\Entity\Server;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_solr\Utility\SolrCommitTrait;
use Drupal\Tests\search_api_solr\Kernel\SolrBackendTestBase;
use Drupal\Tests\search_api_solr\Traits\InvokeMethodTrait;

/**
 * Tests search autocomplete support and ngram results using the Solr search backend.
 *
 * @group search_api_solr
 */
class SearchApiSolrAutocompleteAndNgramTest extends SolrBackendTestBase {

  use SolrCommitTrait;
  use InvokeMethodTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'search_api_autocomplete',
    'search_api_solr_autocomplete',
    'search_api_solr_legacy',
    'user',
  ];

  /**
   * Tests the autocomplete support and ngram results.
   */
  public function testAutocompleteAndNgram(): void {
    $this->addTestEntity(1, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 1 about cats, dogs and trees.',
      'type' => 'article',
      'category' => 'dogs and trees',
    ]);

    // Add another node with body length equal to the limit.
    $this->addTestEntity(2, [
      'name' => 'Test Article 1',
      'body' => 'The test article number 2 about a tree.',
      'type' => 'article',
      'category' => 'trees',
    ]);

    $this->indexItems($this->indexId);

    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = Server::load($this->serverId)->getBackend();
    $solr_major_version = $backend->getSolrConnector()->getSolrMajorVersion();
    $autocompleteSearch = new Search([], 'search_api_autocomplete_search');

    $query = $this->buildSearch(['artic'], [], ['body_unstemmed'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(1, count($suggestions));
    $this->assertEquals('le', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals(2, $suggestions[0]->getResultsCount());

    $query = $this->buildSearch(['artic'], [], ['body'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getTermsSuggestions($query, $autocompleteSearch, 'artic', 'artic');
    $this->assertEquals(1, count($suggestions));
    // This time we test the stemmed token.
    $this->assertEquals('l', $suggestions[0]->getSuggestionSuffix());
    $this->assertEquals(2, $suggestions[0]->getResultsCount());

    $targeted_branch = $backend->getSolrConnector()->getSchemaTargetedSolrBranch();
    if ('4.x' !== $targeted_branch && '3.x' !== $targeted_branch) {
      $query = $this->buildSearch(['articel'], [], ['body'], FALSE);
      $query->setLanguages(['en']);
      $suggestions = $backend->getSpellcheckSuggestions($query, $autocompleteSearch, 'articel', 'articel');
      $this->assertEquals(1, count($suggestions));
      $this->assertEquals('article', $suggestions[0]->getSuggestedKeys());
      $this->assertEquals(0, $suggestions[0]->getResultsCount());
    }

    $query = $this->buildSearch(['article tre'], [], ['body_unstemmed'], FALSE);
    $query->setLanguages(['en']);
    $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'tre', 'article tre');
    $this->assertEquals('article tree', $suggestions[0]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[0]->getResultsCount());
    // Having set preserveOriginal in WordDelimiter let punction remain.
    $this->assertEquals('article tree.', $suggestions[1]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[1]->getResultsCount());
    $this->assertEquals('article trees', $suggestions[2]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[2]->getResultsCount());
    $this->assertEquals('article trees.', $suggestions[3]->getSuggestedKeys());
    $this->assertEquals(1, $suggestions[3]->getResultsCount());

    // @todo spellcheck tests
    // @codingStandardsIgnoreStart
    // $query = $this->buildSearch(['articel cats doks'], [], ['body'], FALSE);
    // $query->setLanguages(['en']);
    // $suggestions = $backend->getSpellcheckSuggestions($query, $autocompleteSearch, 'doks', 'articel doks');
    // $this->assertEquals(1, count($suggestions));
    // $this->assertEquals('article dogs', $suggestions[0]->getSuggestedKeys());

    // $query = $this->buildSearch(['articel tre'], [], ['body'], FALSE);
    // $query->setLanguages(['en']);
    // $suggestions = $backend->getAutocompleteSuggestions($query, $autocompleteSearch, 'tre', 'articel tre');
    // $this->assertEquals(5, count($suggestions));
    // $this->assertEquals('e', $suggestions[0]->getSuggestionSuffix());
    // $this->assertEquals(1, $suggestions[0]->getResultsCount());
    // $this->assertEquals('es', $suggestions[1]->getSuggestionSuffix());
    // @codingStandardsIgnoreEnd

    if (version_compare($solr_major_version, '6', '>=')) {
      // @todo Add more suggester tests.
      $query = $this->buildSearch(['artic'], [], ['body'], FALSE);
      $query->setLanguages(['en']);
      $suggestions = $backend->getSuggesterSuggestions($query, $autocompleteSearch, 'artic', 'artic');
      $this->assertEquals(2, count($suggestions));

      // Since we don't specify the result weights explicitly for this suggester
      // we need to deal with a random order and need predictable array keys.
      foreach ($suggestions as $suggestion) {
        $suggestions[$suggestion->getSuggestedKeys()] = $suggestion;
      }
      $this->assertEquals('artic', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getUserInput());
      $this->assertEquals('The test <b>', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getSuggestionPrefix());
      $this->assertEquals('</b>le number 1 about cats, dogs and trees.', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getSuggestionSuffix());
      $this->assertEquals('The test <b>artic</b>le number 1 about cats, dogs and trees.', $suggestions['The test <b>artic</b>le number 1 about cats, dogs and trees.']->getSuggestedKeys());

      $this->assertEquals('artic', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getUserInput());
      $this->assertEquals('The test <b>', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getSuggestionPrefix());
      $this->assertEquals('</b>le number 2 about a tree.', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getSuggestionSuffix());
      $this->assertEquals('The test <b>artic</b>le number 2 about a tree.', $suggestions['The test <b>artic</b>le number 2 about a tree.']->getSuggestedKeys());
    }

    // Tests NGram and Edge NGram search result.
    foreach (['category_ngram', 'category_edge'] as $field) {
      $results = $this->buildSearch(['tre'], [], [$field])
        ->execute();
      $this->assertResults([1, 2], $results, $field . ': tre');

      $results = $this->buildSearch(['Dog'], [], [$field])
        ->execute();
      $this->assertResults([1], $results, $field . ': Dog');

      $results = $this->buildSearch([], [], [])
        ->addCondition($field, 'Dog')
        ->execute();
      $this->assertResults([1], $results, $field . ': Dog as condition');
    }

    // Tests NGram search result.
    $result_set = [
      'category_ngram' => [1, 2],
      'category_ngram_string' => [1, 2],
      'category_edge' => [],
      'category_edge_string' => [],
    ];
    foreach ($result_set as $field => $expected_results) {
      $results = $this->buildSearch(['re'], [], [$field])
        ->execute();
      $this->assertResults($expected_results, $results, $field . ': re');
    }

    foreach (['category_ngram_string' => [1, 2], 'category_edge_string' => [2]] as $field => $expected_results) {
      $results = $this->buildSearch(['tre'], [], [$field])
        ->execute();
      $this->assertResults($expected_results, $results, $field . ': tre');
    }
  }

}
