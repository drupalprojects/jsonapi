<?php

namespace Drupal\Tests\jsonapi\Kernel\Serializer;

use Drupal\Core\Render\Markup;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;

/**
 * @coversClass \Drupal\jsonapi\Serializer\Serializer
 * @group jsonapi
 *
 * @internal
 */
class SerializerTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'serialization',
    'system',
    'node',
    'user',
    'field',
    'text',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    // Add the additional table schemas.
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
  }

  /**
   * @covers \Drupal\jsonapi\Serializer\Serializer::normalize
   */
  public function testFallbackNormalizer() {
    NodeType::create([
      'type' => 'foo',
    ])->save();

    $this->createTextField('node', 'foo', 'field_text', 'Text');

    $node = Node::create([
      'title' => 'Test Node',
      'type' => 'foo',
      'field_text' => 'This is some text.',
    ]);
    $node->save();

    /** @var \Drupal\jsonapi\Serializer\Serializer $serializer */
    $serializer = $this->container->get('jsonapi.serializer_do_not_use_removal_imminent');

    $value = $serializer->normalize($node->field_text, 'api_json');
    $this->assertTrue($value instanceof FieldNormalizerValue);

    $nested_field = [
      $node->field_text,
    ];

    // When wrapped in an array, we should still be using the JSON API serializer.
    $value = $serializer->normalize($nested_field, 'api_json');
    $this->assertTrue($value[0] instanceof FieldNormalizerValue);

    // Continue to use the fallback normalizer when we need it.
    $data = Markup::create('<h2>Test Markup</h2>');
    $value = $serializer->normalize($data, 'api_json');

    $this->assertEquals('<h2>Test Markup</h2>', $value);
  }
}
