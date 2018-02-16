<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Core\Url;
use Drupal\rest\Entity\RestResourceConfig;

/**
 * JSON API integration test for the "RestResourceConfig" config entity type.
 *
 * @group jsonapi
 */
class RestResourceConfigTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['rest', 'dblog'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'rest_resource_config';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'rest_resource_config--rest_resource_config';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\rest\RestResourceConfigInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer rest resources']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $rest_resource_config = RestResourceConfig::create([
      'id' => 'llama',
      'plugin_id' => 'dblog',
      'granularity' => 'method',
      'configuration' => [
        'GET' => [
          'supported_formats' => [
            'json',
          ],
          'supported_auth' => [
            'cookie',
          ],
        ],
      ],
    ]);
    $rest_resource_config->save();

    return $rest_resource_config;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $self_url = Url::fromUri('base:/jsonapi/rest_resource_config/rest_resource_config/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'rest_resource_config--rest_resource_config',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'uuid' => $this->entity->uuid(),
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [
            // @todo Remove the first 3 lines in favor of the 5 commented lines in https://www.drupal.org/project/jsonapi/issues/2942979
            // @codingStandardsIgnoreStart
            'dblog',
            'serialization',
            'user',
//            'module' => [
//              'dblog',
//              'serialization',
//              'user',
//            ],
            // @codingStandardsIgnoreEnd
          ],
          'id' => 'llama',
          'plugin_id' => 'dblog',
          'granularity' => 'method',
          'configuration' => [
            // @todo Remove the first 6 lines in favor of the 8 commented lines in https://www.drupal.org/project/jsonapi/issues/2942979
            // @codingStandardsIgnoreStart
            'supported_formats' => [
              'json',
            ],
            'supported_auth' => [
              'cookie',
            ],
//            'GET' => [
//              'supported_formats' => [
//                'json',
//              ],
//              'supported_auth' => [
//                'cookie',
//              ],
//            ],
            // @codingStandardsIgnoreEnd
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

  // @codingStandardsIgnoreStart
  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    // @todo Uncomment first line, remove second line in https://www.drupal.org/project/jsonapi/issues/2940342.
//    return ['user.permissions'];
    return parent::getExpectedCacheContexts();
  }
  // @codingStandardsIgnoreEnd

}
