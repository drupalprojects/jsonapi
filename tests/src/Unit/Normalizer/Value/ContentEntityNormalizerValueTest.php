<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\EntityReferenceNormalizerValueInterface;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;
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
   * @covers ::rasterizeValue
   */
  public function testRasterizeValue() {
    $field1 = $this->prophesize(FieldNormalizerValueInterface::class);
    $field1->getIncludes()->willReturn([]);
    $field1->getPropertyType()->willReturn('attributes');
    $field1->rasterizeValue()->willReturn('dummy_title');
    $field2 = $this->prophesize(EntityReferenceNormalizerValueInterface::class);
    $field2->getPropertyType()->willReturn('relationships');
    $field2->rasterizeValue()->willReturn(['data' => ['type' => 'node', 'id' => 2]]);
    $included = $this->prophesize(ContentEntityNormalizerValueInterface::class);
    $field2->getIncludes()->willReturn([$included]);
    $context = ['resource_path' => 'node'];
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
    $object = new ContentEntityNormalizerValue(
      ['title' => $field1->reveal(), 'field_related' => $field2->reveal()],
      $context,
      $entity->reveal(),
      $link_manager->reveal(),
      $entity_type_manager->reveal()
    );
    $this->assertEquals([
      'type' => 'node',
      'id' => 1,
      'data' => [
        'attributes' => ['title' => 'dummy_title'],
        'relationships' => [
          'field_related' => ['data' => ['type' => 'node', 'id' => 2]],
        ],
      ],
      'links' => [
        'self' => 'dummy_entity_link',
        'type' => 'dummy_type_link',
      ],
    ], $object->rasterizeValue());
  }

}
