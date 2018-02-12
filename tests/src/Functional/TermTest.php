<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * JSON API integration test for the "Term" content entity type.
 *
 * @group jsonapi
 */
class TermTest extends ResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['taxonomy', 'path'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'taxonomy_term';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'taxonomy_term--camelids';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
  ];

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access content']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['create terms in camelids']);
        break;

      case 'PATCH':
        // Grant the 'create url aliases' permission to test the case when
        // the path field is accessible, see
        // \Drupal\Tests\rest\Functional\EntityResource\Node\NodeResourceTestBase
        // for a negative test.
        $this->grantPermissionsToTestedRole(['edit terms in camelids', 'create url aliases']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['delete terms in camelids']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $vocabulary = Vocabulary::load('camelids');
    if (!$vocabulary) {
      // Create a "Camelids" vocabulary.
      $vocabulary = Vocabulary::create([
        'name' => 'Camelids',
        'vid' => 'camelids',
      ]);
      $vocabulary->save();
    }

    // Create a "Llama" taxonomy term.
    $term = Term::create(['vid' => $vocabulary->id()])
      ->setName('Llama')
      ->setDescription("It is a little known fact that llamas cannot count higher than seven.")
      ->setChangedTime(123456789)
      ->set('path', '/llama');
    $term->save();

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $self_url = Url::fromUri('base:/jsonapi/taxonomy_term/camelids/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();

    // We test with multiple parent terms, and combinations thereof.
    // @see ::createEntity()
    // @see ::testGetIndividual()
    // @see ::testGetIndividualTermWithParent()
    // @see ::providerTestGetIndividualTermWithParent()
    $parent_term_ids = [];
    for ($i = 0; $i < $this->entity->get('parent')->count(); $i++) {
      $parent_term_ids[$i] = (int) $this->entity->get('parent')[$i]->target_id;
    }

    $expected_parent_normalization = FALSE;
    switch ($parent_term_ids) {
      case [0]:
        // @todo This is missing the root parent, fix this in https://www.drupal.org/project/jsonapi/issues/2940339
        $expected_parent_normalization = [
          'data' => [],
        ];
        break;

      case [2]:
        $expected_parent_normalization = [
          'data' => [
            [
              'id' => Term::load(2)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;

      case [0, 2]:
        $expected_parent_normalization = [
          'data' => [
            // @todo This is missing the root parent, fix this in https://www.drupal.org/project/jsonapi/issues/2940339
            [
              'id' => Term::load(2)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;

      case [3, 2]:
        $expected_parent_normalization = [
          'data' => [
            [
              'id' => Term::load(3)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
            [
              'id' => Term::load(2)->uuid(),
              'type' => 'taxonomy_term--camelids',
            ],
          ],
          'links' => [
            'related' => $self_url . '/parent',
            'self' => $self_url . '/relationships/parent',
          ],
        ];
        break;
    }
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => 'http://jsonapi.org/format/1.0/',
          ],
        ],
        'version' => '1.0',
      ],
      'links' => [
        'self' => $self_url,
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'taxonomy_term--camelids',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'changed' => $this->entity->getChangedTime(),
          // @todo uncomment this in https://www.drupal.org/project/jsonapi/issues/2929932
          /* 'changed' => $this->formatExpectedTimestampItemValues($this->entity->getChangedTime()), */
          'default_langcode' => TRUE,
          'description' => [
            'value' => 'It is a little known fact that llamas cannot count higher than seven.',
            'format' => NULL,
            // @todo Uncomment in https://www.drupal.org/project/jsonapi/issues/2921257.
            /* 'processed' => "<p>It is a little known fact that llamas cannot count higher than seven.</p>\n", */
          ],
          'langcode' => 'en',
          'name' => 'Llama',
          'path' => [
            'alias' => '/llama',
            'pid' => 1,
            'langcode' => 'en',
          ],
          'tid' => 1,
          'uuid' => $this->entity->uuid(),
          'weight' => 0,
        ],
        'relationships' => [
          'parent' => $expected_parent_normalization,
          'vid' => [
            'data' => [
              'id' => Vocabulary::load('camelids')->uuid(),
              'type' => 'taxonomy_vocabulary--taxonomy_vocabulary',
            ],
            'links' => [
              'related' => $self_url . '/vid',
              'self' => $self_url . '/relationships/vid',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'data' => [
        'type' => 'taxonomy_term--camelids',
        'attributes' => [
          'name' => 'Dramallama',
          'description' => [
            'value' => 'Dramallamas are the coolest camelids.',
            'format' => NULL,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return "The 'access content' permission is required.";

      case 'POST':
        return "The following permissions are required: 'create terms in camelids' OR 'administer taxonomy'.";

      case 'PATCH':
        return "The following permissions are required: 'edit terms in camelids' OR 'administer taxonomy'.";

      case 'DELETE':
        return "The following permissions are required: 'delete terms in camelids' OR 'administer taxonomy'.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * Tests PATCHing a term's path.
   *
   * For a negative test, see the similar test coverage for Node.
   *
   * @see \Drupal\Tests\jsonapi\Functional\NodeTest::testPatchPath()
   * @see \Drupal\Tests\rest\Functional\EntityResource\Node\NodeResourceTestBase::testPatchPath()
   */
  public function testPatchPath() {
    $this->setUpAuthorization('GET');
    $this->setUpAuthorization('PATCH');

    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), [static::$entityTypeId => $this->entity->uuid()]);
    /* $url = $this->entity->toUrl('jsonapi'); */

    // GET term's current normalization.
    $response = $this->request('GET', $url, $this->getAuthenticationRequestOptions('GET'));
    $normalization = Json::decode((string) $response->getBody());

    // Change term's path alias.
    $normalization['data']['attributes']['path']['alias'] .= 's-rule-the-world';

    // Create term PATCH request.
    $request_options = $this->getAuthenticationRequestOptions('PATCH');
    $request_options[RequestOptions::BODY] = Json::encode($normalization);

    // PATCH request: 200.
    $response = $this->request('PATCH', $url, $request_options);
    // @todo investigate this more (cache tags + contexts), cfr https://www.drupal.org/project/drupal/issues/2626298 + https://www.drupal.org/project/jsonapi/issues/2933939
    $this->assertResourceResponse(200, FALSE, $response, ['http_response', 'taxonomy_term:1'], $this->getExpectedCacheContexts());
    $updated_normalization = Json::decode((string) $response->getBody());
    $this->assertSame($normalization['data']['attributes']['path']['alias'], $updated_normalization['data']['attributes']['path']['alias']);
  }

  // @codingStandardsIgnoreStart
  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags() {
    // @todo Uncomment first line, remove second line in https://www.drupal.org/project/jsonapi/issues/2940342.
//    return Cache::mergeTags(parent::getExpectedCacheTags(), ['config:filter.format.plain_text', 'config:filter.settings']);
    return parent::getExpectedCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    // @todo Uncomment first line, remove second line in https://www.drupal.org/project/jsonapi/issues/2940342.
//    return Cache::mergeContexts(['url.site'], $this->container->getParameter('renderer.config')['required_cache_contexts']);
    return parent::getExpectedCacheContexts();
  }

  // @codingStandardsIgnoreEnd

  /**
   * Tests GETting a term with a parent term other than the default <root> (0).
   *
   * @see ::getExpectedNormalizedEntity()
   *
   * @dataProvider providerTestGetIndividualTermWithParent
   */
  public function testGetIndividualTermWithParent(array $parent_term_ids) {
    // Create all possible parent terms.
    Term::create(['vid' => Vocabulary::load('camelids')->id()])
      ->setName('Lamoids')
      ->save();
    Term::create(['vid' => Vocabulary::load('camelids')->id()])
      ->setName('Wimoids')
      ->save();

    // Modify the entity under test to use the provided parent terms.
    $this->entity->set('parent', $parent_term_ids)->save();

    // @todo Remove line below in favor of commented line in https://www.drupal.org/project/jsonapi/issues/2878463.
    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), [static::$entityTypeId => $this->entity->uuid()]);
    /* $url = $this->entity->toUrl('jsonapi'); */
    $request_options = $this->getAuthenticationRequestOptions('GET');
    $this->setUpAuthorization('GET');
    $response = $this->request('GET', $url, $request_options);
    $expected = $this->getExpectedNormalizedEntity();
    static::recursiveKSort($expected);
    $actual = Json::decode((string) $response->getBody());
    static::recursiveKSort($actual);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for ::testGetIndividualTermWithParent().
   */
  public function providerTestGetIndividualTermWithParent() {
    return [
      'root parent: [0] (= no parent)' => [
        [0],
      ],
      'non-root parent: [2]' => [
        [2],
      ],
      'multiple parents: [0,2] (root + non-root parent)' => [
        [0, 2],
      ],
      'multiple parents: [3,2] (both non-root parents)' => [
        [3, 2],
      ],
    ];
  }

}
