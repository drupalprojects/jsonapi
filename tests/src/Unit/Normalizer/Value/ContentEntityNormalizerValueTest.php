<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\DocumentRootNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\EntityReferenceNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Class ContentEntityNormalizerValueTest.
 *
 * @package Drupal\Tests\jsonapi\Unit\Normalizer\Value
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValue
 * @group jsonapi
 */
class ContentEntityNormalizerValueTest extends UnitTestCase{

  /**
   * The ContentEntityNormalizerValue object.
   *
   * @var ContentEntityNormalizerValueInterface
   */
  protected $object;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $field1 = $this->prophesize(FieldNormalizerValueInterface::class);
    $field1->getIncludes()->willReturn([]);
    $field1->getPropertyType()->willReturn('attributes');
    $field1->rasterizeValue()->willReturn('dummy_title');
    $field2 = $this->prophesize(EntityReferenceNormalizerValueInterface::class);
    $field2->getPropertyType()->willReturn('relationships');
    $field2->rasterizeValue()->willReturn(['data' => ['type' => 'node', 'id' => 2]]);
    $included[] = $this->prophesize(DocumentRootNormalizerValueInterface::class);
    $included[0]->getIncludes()->willReturn([]);
    $included[0]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => 3,
        'attributes' => ['body' => 'dummy_body1'],
      ],
    ]);
    // Type & id duplicated in purpose.
    $included[] = $this->prophesize(DocumentRootNormalizerValueInterface::class);
    $included[1]->getIncludes()->willReturn([]);
    $included[1]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => 3,
        'attributes' => ['body' => 'dummy_body2'],
      ],
    ]);
    $included[] = $this->prophesize(DocumentRootNormalizerValueInterface::class);
    $included[2]->getIncludes()->willReturn([]);
    $included[2]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => 4,
        'attributes' => ['body' => 'dummy_body3'],
      ],
    ]);
    $field2->getIncludes()->willReturn(array_map(function ($included_item) {
      return $included_item->reveal();
    }, $included));
    $resource_config = $this->prophesize(ResourceConfigInterface::class);
    $resource_config->getTypeName()->willReturn('node');
    $context = ['resource_config' => $resource_config->reveal()];
    $entity = $this->prophesize(EntityInterface::class);
    $entity->id()->willReturn(1);
    $entity->isNew()->willReturn(FALSE);
    $entity->getEntityTypeId()->willReturn('node');
    $entity->bundle()->willReturn('article');
    $link_manager = $this->prophesize(LinkManagerInterface::class);
    $link_manager
      ->getEntityLink(Argument::any(), Argument::any(), Argument::type('array'), Argument::type('string'))
      ->willReturn('dummy_entity_link');
    $this->object = new ContentEntityNormalizerValue(
      ['title' => $field1->reveal(), 'field_related' => $field2->reveal()],
      $context,
      $entity->reveal(),
      ['link_manager' => $link_manager->reveal()]
    );
  }


  /**
   * @covers ::rasterizeValue
   */
  public function testRasterizeValue() {
    $this->assertEquals([
      'type' => 'node',
      'id' => 1,
      'attributes' => ['title' => 'dummy_title'],
      'relationships' => [
        'field_related' => ['data' => ['type' => 'node', 'id' => 2]],
      ],
      'links' => [
        'self' => 'dummy_entity_link',
      ],
    ], $this->object->rasterizeValue());
  }

  /**
   * @covers ::rasterizeIncludes
   */
  public function testRasterizeIncludes() {
    $expected = [
      [
        'data' => [
          'type' => 'node',
          'id' => 3,
          'attributes' => ['body' => 'dummy_body1'],
        ],
      ],
      [
        'data' => [
          'type' => 'node',
          'id' => 3,
          'attributes' => ['body' => 'dummy_body2'],
        ],
      ],
      [
        'data' => [
          'type' => 'node',
          'id' => 4,
          'attributes' => ['body' => 'dummy_body3'],
        ],
      ],
    ];
    $this->assertEquals($expected, $this->object->rasterizeIncludes());
  }

  /**
   * @covers ::getIncludes
   */
  public function testGetIncludes() {
    $includes = $this->object->getIncludes();
    $includes = array_filter($includes, function ($included) {
      return $included instanceof DocumentRootNormalizerValueInterface;
    });
    $this->assertCount(3, $includes);
  }

}
