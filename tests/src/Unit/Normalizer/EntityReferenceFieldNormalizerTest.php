<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
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
    $item_definition = $this->prophesize(FieldStorageDefinitionInterface::class);
    $item_definition->getMainPropertyName()->willReturn('bunny');
    $item_definition->getSetting('target_type')->willReturn('fake_entity_type');
    $item_definition->getSetting('handler_settings')->willReturn([
      'target_bundles' => ['dummy_bundle'],
    ]);
    $field_definition->getItemDefinition()
      ->willReturn($item_definition->reveal());
    $field_manager->getFieldDefinitions('fake_entity_type', 'dummy_bundle')
      ->willReturn([
        'field_dummy' => $field_definition->reveal(),
      ]);
    $plugin_manager = $this->prophesize(FieldTypePluginManagerInterface::class);
    $plugin_manager->createFieldItemList(
      Argument::type(FieldableEntityInterface::class),
      'field_dummy',
      Argument::type('array')
    )->willReturnArgument(2);
    $resource_manager = $this->prophesize(ResourceManagerInterface::class);
    $resource_config = $this->prophesize(ResourceConfigInterface::class);
    $resource_config->getTypeName()->willReturn('lorem');
    $resource_manager->get('fake_entity_type', 'dummy_bundle')
      ->willReturn($resource_config->reveal());
    $this->normalizer = new EntityReferenceFieldNormalizer(
      $link_manager->reveal(),
      $field_manager->reveal(),
      $plugin_manager->reveal(),
      $resource_manager->reveal()
    );
  }

  /**
   * @covers ::denormalize
   */
  public function testDenormalize() {
    $data = ['data' => [['type' => 'lorem', 'id' => 42]]];
    $resource_config = $this->prophesize(ResourceConfigInterface::class);
    $resource_config->getEntityTypeId()->willReturn('fake_entity_type');
    $resource_config->getBundleId()->willReturn('dummy_bundle');
    $entity = $this->prophesize(FieldableEntityInterface::class);
    $context = [
      'resource_config' => $resource_config->reveal(),
      'related' => 'field_dummy',
      'target_entity' => $entity->reveal(),
    ];
    $denormalized = $this->normalizer->denormalize($data, NULL, 'api_json', $context);
    $this->assertSame([['bunny' => 42]], $denormalized);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testDenormalizeInvalidResource() {
    $data = ['data' => [['type' => 'invalid', 'id' => 42]]];
    $resource_config = $this->prophesize(ResourceConfigInterface::class);
    $resource_config->getEntityTypeId()->willReturn('fake_entity_type');
    $resource_config->getBundleId()->willReturn('dummy_bundle');
    $context = [
      'resource_config' => $resource_config->reveal(),
      'related' => 'field_dummy',
      'target_entity' => $this->prophesize(FieldableEntityInterface::class)->reveal(),
    ];
    $this->normalizer->denormalize($data, NULL, 'api_json', $context);
  }

}
