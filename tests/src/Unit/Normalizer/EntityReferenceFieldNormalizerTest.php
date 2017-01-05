<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\field\Entity\FieldConfig;
use Drupal\jsonapi\Configuration\ResourceConfig;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\Normalizer\EntityReferenceFieldNormalizer;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Class EntityReferenceFieldNormalizerTest.
 *
 * @package Drupal\Tests\serialization\Unit\Normalizer
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\EntityReferenceFieldNormalizer
 *
 * @group jsonapi
 */
class EntityReferenceFieldNormalizerTest extends UnitTestCase {

  /**
   * The normalizer under test.
   *
   * @var \Drupal\jsonapi\Normalizer\EntityReferenceFieldNormalizer
   */
  protected $normalizer;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $link_manager = $this->prophesize(LinkManagerInterface::class);
    $field_manager = $this->prophesize(EntityFieldManagerInterface::class);
    $field_definition = $this->prophesize(FieldConfig::class);
    $item_definition = $this->prophesize(FieldItemDataDefinition::class);
    $item_definition->getMainPropertyName()->willReturn('bunny');
    $item_definition->getSetting('target_type')->willReturn('fake_entity_type');
    $item_definition->getSetting('handler_settings')->willReturn([
      'target_bundles' => ['dummy_bundle'],
    ]);
    $field_definition->getItemDefinition()
      ->willReturn($item_definition->reveal());
    $storage_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $storage_definition->isMultiple()->willReturn(TRUE);
    $field_definition->getFieldStorageDefinition()->willReturn($storage_definition->reveal());

    $field_definition2 = $this->prophesize(FieldConfig::class);
    $field_definition2->getItemDefinition()
      ->willReturn($item_definition->reveal());
    $storage_definition2 = $this->prophesize(FieldStorageDefinitionInterface::class);
    $storage_definition2->isMultiple()->willReturn(FALSE);
    $field_definition2->getFieldStorageDefinition()->willReturn($storage_definition2->reveal());

    $field_manager->getFieldDefinitions('fake_entity_type', 'dummy_bundle')
      ->willReturn([
        'field_dummy' => $field_definition->reveal(),
        'field_dummy_single' => $field_definition2->reveal(),
      ]);
    $plugin_manager = $this->prophesize(FieldTypePluginManagerInterface::class);
    $plugin_manager->createFieldItemList(
      Argument::type(FieldableEntityInterface::class),
      Argument::type('string'),
      Argument::type('array')
    )->willReturnArgument(2);
    $resource_manager = $this->prophesize(ResourceManagerInterface::class);
    $resource_manager->get('fake_entity_type', 'dummy_bundle')
      ->willReturn(new ResourceConfig('lorem', 'dummy_bundle', NULL));

    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $entity = $this->prophesize(EntityInterface::class);
    $entity->uuid()->willReturn('4e6cb61d-4f04-437f-99fe-42c002393658');
    $entity->id()->willReturn(42);
    $entity_storage->loadByProperties(Argument::type('array'))
      ->willReturn([$entity->reveal()]);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('lorem')
      ->willReturn($entity_storage->reveal());
    $resource_manager->getEntityTypeManager()->willReturn($entity_type_manager->reveal());

    $this->normalizer = new EntityReferenceFieldNormalizer(
      $link_manager->reveal(),
      $field_manager->reveal(),
      $plugin_manager->reveal(),
      $resource_manager->reveal()
    );
  }

  /**
   * @covers ::denormalize
   * @dataProvider denormalizeProvider
   */
  public function testDenormalize($input, $field_name, $expected) {
    $resource_config = new ResourceConfig('fake_entity_type', 'dummy_bundle', NULL);
    $entity = $this->prophesize(FieldableEntityInterface::class);
    $context = [
      'resource_config' => $resource_config,
      'related' => $field_name,
      'target_entity' => $entity->reveal(),
    ];
    $denormalized = $this->normalizer->denormalize($input, NULL, 'api_json', $context);
    $this->assertSame($expected, $denormalized);
  }

  /**
   * Data provider for the denormalize test.
   *
   * @return array
   *   The data for the test method.
   */
  public function denormalizeProvider() {
    return [
      [
        ['data' => [['type' => 'lorem--dummy_bundle', 'id' => '4e6cb61d-4f04-437f-99fe-42c002393658']]],
        'field_dummy',
        [['bunny' => 42]],
      ],
      [
        ['data' => []],
        'field_dummy',
        [],
      ],
      [
        ['data' => NULL],
        'field_dummy_single',
        [],
      ],
    ];
  }

  /**
   * @covers ::denormalize
   * @expectedException \Drupal\jsonapi\Error\SerializableHttpException
   * @dataProvider denormalizeInvalidResourceProvider
   */
  public function testDenormalizeInvalidResource($data, $field_name) {
    $resource_config = new ResourceConfig('fake_entity_type', 'dummy_bundle', NULL);
    $context = [
      'resource_config' => $resource_config,
      'related' => $field_name,
      'target_entity' => $this->prophesize(FieldableEntityInterface::class)->reveal(),
    ];
    $this->normalizer->denormalize($data, NULL, 'api_json', $context);
  }

  /**
   * Data provider for the denormalize test.
   *
   * @return array
   *   The input data for the test method.
   */
  public function denormalizeInvalidResourceProvider() {
    return [
      [['data' => [['type' => 'invalid', 'id' => '4e6cb61d-4f04-437f-99fe-42c002393658']]], 'field_dummy'],
      [['data' => ['type' => 'lorem', 'id' => '4e6cb61d-4f04-437f-99fe-42c002393658']], 'field_dummy'],
      [['data' => [['type' => 'lorem', 'id' => '4e6cb61d-4f04-437f-99fe-42c002393658']]], 'field_dummy_single'],
    ];
  }

}
