<?php

namespace Drupal\Tests\jsonapi\Unit\Routing\Param;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\jsonapi\Routing\Param\Filter;
use Drupal\Tests\UnitTestCase;


/**
 * Class FilterTest.
 *
 * @package Drupal\jsonapi\Test\Unit
 *
 * @coversDefaultClass \Drupal\jsonapi\Routing\Param\Filter
 *
 * @group jsonapi
 */
class FilterTest extends UnitTestCase {

  /**
   * @covers ::get
   * @dataProvider getProvider
   */
  public function testGet($original, $expected) {
    $pager = new Filter(
      $original,
      'lorem',
      $this->prophesize(EntityFieldManagerInterface::class)->reveal());
    $this->assertEquals($expected, $pager->get());
  }

  /**
   * Data provider for testGet.
   */
  public function getProvider() {
    return [
      [
        [0 => ['field' => 'foo', 'value' => 'bar']],
        [0 => ['condition' => [ 'field' => 'foo', 'value' => 'bar', 'operator' => '=']]],
      ],
      [
        ['foo' => ['value' => 'bar']],
        ['foo' => ['condition' => [ 'field' => 'foo', 'value' => 'bar', 'operator' => '=']]],
      ],
      [
        [
          0 => ['field' => 'foo', 'value' => 'bar'],
          1 => ['condition' => [ 'field' => 'baz', 'value' => 'zab', 'operator' => '<>']],
        ],
        [
          0 => ['condition' => [ 'field' => 'foo', 'value' => 'bar', 'operator' => '=']],
          1 => ['condition' => [ 'field' => 'baz', 'value' => 'zab', 'operator' => '<>']],
        ],
      ],
      [
        [
          0 => ['field' => 'foo', 'value' => ['bar', 'baz']],
        ],
        [
          0 => ['condition' => [ 'field' => 'foo', 'value' => ['bar', 'baz'], 'operator' => '=']],
        ],
      ],
    ];
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetFail() {
    $pager = new Filter(
      'lorem',
      'ipsum',
      $this->prophesize(EntityFieldManagerInterface::class)->reveal()
    );
    $pager->get();
  }

}
