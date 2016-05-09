<?php

namespace Drupal\Tests\jsonapi\Unit\Normalizer\Value;

use Drupal\jsonapi\Normalizer\Value\EntityReferenceItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\EntityReferenceNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\Tests\UnitTestCase;

/**
 * Class EntityReferenceNormalizerValueTes.
 *
 * @package Drupal\Tests\jsonapi\Unit\Normalizer\Value
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\Value\EntityReferenceNormalizerValue
 * @group jsonapi
 */
class EntityReferenceNormalizerValueTest extends UnitTestCase {

  /**
   * @covers ::rasterizeValue
   * @dataProvider rasterizeValueProvider
   */
  public function testRasterizeValue($values, $cardinality, $expected) {
    $object = new EntityReferenceNormalizerValue($values, $cardinality);
    $this->assertEquals($expected, $object->rasterizeValue());
  }

  /**
   * Data provider fortestRasterizeValue.
   */
  public function rasterizeValueProvider() {
    $uid_raw = 1;
    $uid1 = $this->prophesize(EntityReferenceItemNormalizerValue::class);
    $uid1->rasterizeValue()->willReturn(['type' => 'user', 'id' => $uid_raw++]);
    $uid1->getInclude()->willReturn(NULL);
    $uid2 = $this->prophesize(EntityReferenceItemNormalizerValue::class);
    $uid2->rasterizeValue()->willReturn(['type' => 'user', 'id' => $uid_raw]);
    $uid2->getInclude()->willReturn(NULL);
    return [
      [[$uid1->reveal()], 1, ['data' => ['type' => 'user', 'id' => 1]]],
      [
        [$uid1->reveal(), $uid2->reveal()], 2, [
          'data' => [
            ['type' => 'user', 'id' => 1],
            ['type' => 'user', 'id' => 2],
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::rasterizeValue
   *
   * @expectedException \RuntimeException
   */
  public function testRasterizeValueFails() {
    $uid1 = $this->prophesize(FieldItemNormalizerValue::class);
    $uid1->rasterizeValue()->willReturn(1);
    $uid1->getInclude()->willReturn(NULL);
    $object = new EntityReferenceNormalizerValue([$uid1->reveal()], 1);
    $object->rasterizeValue();
    // If the exception was not thrown, then the following fails.
    $this->assertTrue(FALSE);
  }

}
