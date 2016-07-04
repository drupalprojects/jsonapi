<?php

namespace Drupal\Tests\jsonapi\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simpletest\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class EntityResourceSchemaTest.
 *
 * @package Drupal\Tests\jsonapi\Kernel
 *
 * @coversDefaultClass \Drupal\jsonapi\Controller\SchemaController
 *
 * @group jsonapi
 */
class EntityResourceSchemaTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['jsonapi', 'entity_test', 'user', 'system', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig('jsonapi');

    $this->installEntitySchema('user');
    $this->installSchema('system', 'sequences');

    $account = $this->createUser(['access content']);
    \Drupal::currentUser()->setAccount($account);
  }

  public function testEntitySchema() {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $kernel */
    $kernel = \Drupal::service('http_kernel');

    $result = $kernel->handle(Request::create('/api/entity_test/entity_test/scheme'));
    $this->assertEquals(200, $result->getStatusCode());

    $data = json_decode($result->getContent(), TRUE);
    $this->assertEquals('object', $data['type']);
    $this->assertEquals('object', $data['properties']['data']['type']);
    $this->assertEquals('object', $data['properties']['data']['properties']['attributes']['type']);

    $attributes_schema = $data['properties']['data']['properties']['attributes'];

    $this->assertEntityField($attributes_schema, 'id', 'ID', ['value' => 'integer'], ['value']);
    $this->assertEntityField($attributes_schema, 'uuid', 'UUID', ['value' => 'string'], ['value']);
    $this->assertEntityField($attributes_schema, 'langcode', 'Language', ['value' => 'string'], ['value']);
    $this->assertEntityField($attributes_schema, 'type', NULL, ['value' => 'string'], ['value']);
    $this->assertEntityField($attributes_schema, 'name', 'Name', ['value' => 'string'], ['value']);
    $this->assertEntityField($attributes_schema, 'created', 'Authored on', ['value' => 'integer'], ['value']);
    $this->assertEntityField($attributes_schema, 'user_id', 'User ID', ['target_id' => 'integer'], ['target_id']);
  }

  protected function assertEntityField($attributes_schema, $name, $title, array $properties, array $required_properties) {
    if ($title) {
      $this->assertEquals($title, $attributes_schema['properties'][$name]['title']);
    }
    $this->assertEquals('array', $attributes_schema['properties'][$name]['type']);
    $this->assertEquals('object', $attributes_schema['properties'][$name]['items']['type']);

    foreach ($properties as $property_name => $type) {
      $this->assertEquals($type, $attributes_schema['properties'][$name]['items']['properties'][$property_name]['type']);
    }
    $this->assertEquals($required_properties, $attributes_schema['properties'][$name]['items']['required']);
  }

}
