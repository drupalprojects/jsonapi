<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;

use Drupal\jsonapi\Normalizer\Value\EntityReferenceItemNormalizerValue;
use Drupal\Tests\UnitTestCase;

/**
 * Class EntityReferenceItemNormalizerValueTest.
 *
 * @package Drupal\Tests\jsonapi\Unit\Normalizer\Value
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\Value\EntityReferenceItemNormalizerValue
 * @group jsonapi
 */
class EntityReferenceItemNormalizerValueTest extends UnitTestCase {

  /**
   * @covers ::rasterizeValue
   * @dataProvider rasterizeValueProvider
   */
  public function testRasterizeValue($values, $resource, $expected) {
    $object = new EntityReferenceItemNormalizerValue($values, $resource);
    $this->assertEquals($expected, $object->rasterizeValue());
  }

  /**
   * Data provider for testRasterizeValue.
   */
  public function rasterizeValueProvider() {
    return [
      [['target_id' => 1], 'node', ['type' => 'node', 'id' => 1]],
      [['value' => 1], 'node', ['type' => 'node', 'id' => 1]],
      [[1], 'node', ['type' => 'node', 'id' => 1]],
      [[], 'node', []],
      [[NULL], 'node', NULL],
    ];
  }
}
