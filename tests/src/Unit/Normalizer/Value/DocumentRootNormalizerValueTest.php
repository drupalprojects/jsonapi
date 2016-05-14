<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\DocumentRootNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\DocumentRootNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\EntityReferenceNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Class DocumentRootNormalizerValueTest.
 *
 * @package Drupal\Tests\jsonapi\Unit\Normalizer\Value
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\Value\DocumentRootNormalizerValue
 * @group jsonapi
 */
class DocumentRootNormalizerValueTest extends UnitTestCase{

  /**
   * The DocumentRootNormalizerValue object.
   *
   * @var DocumentRootNormalizerValueInterface
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
    $included[] = $this->prophesize(DocumentRootNormalizerValue::class);
    $included[0]->getIncludes()->willReturn([]);
    $included[0]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => 3,
        'attributes' => ['body' => 'dummy_body1'],
      ],
    ]);
    // Type & id duplicated in purpose.
    $included[] = $this->prophesize(DocumentRootNormalizerValue::class);
    $included[1]->getIncludes()->willReturn([]);
    $included[1]->rasterizeValue()->willReturn([
      'data' => [
        'type' => 'node',
        'id' => 3,
        'attributes' => ['body' => 'dummy_body2'],
      ],
    ]);
    $included[] = $this->prophesize(DocumentRootNormalizerValue::class);
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
    $entity->hasLinkTemplate(Argument::type('string'))->willReturn(TRUE);
    $url = $this->prophesize(Url::class);
    $url->toString()->willReturn('dummy_entity_link');
    $url->setRouteParameter(Argument::any(), Argument::any())->willReturn($url->reveal());
    $entity->toUrl(Argument::type('string'), Argument::type('array'))->willReturn($url->reveal());
    $link_manager = $this->prophesize(LinkManagerInterface::class);
    $link_manager->getTypeUri(Argument::type('string'), Argument::type('string'), Argument::type('array'))->willReturn('dummy_type_link');
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->object = new DocumentRootNormalizerValue(
      ['title' => $field1->reveal(), 'field_related' => $field2->reveal()],
      $context,
      $entity->reveal(),
      $link_manager->reveal(),
      $entity_type_manager->reveal()
    );
  }

  /**
   * @covers ::getIncludes
   */
  public function testGetIncludes() {
    $includes = $this->object->getIncludes();
    $includes = array_filter($includes, function ($included) {
      return $included instanceof DocumentRootNormalizerValueInterface;
    });
    $this->assertCount(2, $includes);
  }

}
