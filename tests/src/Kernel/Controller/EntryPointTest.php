<?php

namespace Drupal\Tests\jsonapi\Kernel\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\jsonapi\Controller\EntryPoint;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use Drupal\user\Entity\User;

/**
 * @coversDefaultClass \Drupal\jsonapi\Controller\EntryPoint
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class EntryPointTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'jsonapi',
    'serialization',
    'system',
    'user',
  ];

  /**
   * @covers ::index
   */
  public function testIndex() {
    $controller = new EntryPoint(
      \Drupal::service('jsonapi.resource_type.repository'),
      \Drupal::service('renderer'),
      \Drupal::service('current_user')
    );
    $processed_response = $controller->index();
    $this->assertEquals(
      [
        'url.site',
        'user.roles:authenticated'
      ],
      $processed_response->getCacheableMetadata()->getCacheContexts()
    );
    $data = json_decode($processed_response->getContent(), TRUE);
    $links = $data['links'];
    $this->assertRegExp('/.*\/jsonapi/', $links['self']);
    $this->assertRegExp('/.*\/jsonapi\/user\/user/', $links['user--user']);
    $this->assertRegExp('/.*\/jsonapi\/node_type\/node_type/', $links['node_type--node_type']);
    $this->assertFalse(array_key_exists('meta', $data));
  }

}
