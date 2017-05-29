<?php

namespace Drupal\Tests\jsonapi\Unit\Routing\Param;

use Drupal\jsonapi\Routing\Param\Filter;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Routing\Param\Filter
 * @group jsonapi
 * @group jsonapi_params
 */
class FilterTest extends UnitTestCase {

  /**
   * @covers ::get
   * @dataProvider getProvider
   */
  public function testGet($original, $expected) {
    $filter = new Filter($original);
    $this->assertEquals($expected, $filter->get());
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetFail() {
    $filter = new Filter('lorem');
    $filter->get();
  }

  /**
   * Data provider for testGet.
   */
  public function getProvider() {
    return [
      // Tests filter[foo][value]=bar.
      [
        ['foo' => ['value' => 'bar']],
        ['foo' => ['condition' => ['path' => 'foo', 'value' => 'bar', 'operator' => '=']]],
      ],
      // Tests filter[0][path]=foo&filter[0][value]=bar.
      [
        [0 => ['path' => 'foo', 'value' => 'bar']],
        [0 => ['condition' => ['path' => 'foo', 'value' => 'bar', 'operator' => '=']]],
      ],
      // Tests filter[foo][value]=bar&filter[foo][operator]=>.
      [
        ['foo' => ['value' => 'bar', 'operator' => '>']],
        ['foo' => ['condition' => ['path' => 'foo', 'value' => 'bar', 'operator' => '>']]],
      ],
      // Tests filter[0][path]=foo&filter[0][value]=1&filter[0][operator]=>.
      [
        [0 => ['path' => 'foo', 'value' => '1', 'operator' => '>']],
        [0 => ['condition' => ['path' => 'foo', 'value' => '1', 'operator' => '>']]],
      ],
      // Tests filter[foo][value][]=1&filter[foo][value][]=2&
      // filter[foo][value][]=3&filter[foo][operator]="NOT IN".
      [
        ['foo' => ['value' => ['1', '2', '3'], 'operator' => 'NOT IN']],
        ['foo' => ['condition' => ['path' => 'foo', 'value' => ['1', '2', '3'], 'operator' => 'NOT IN']]],
      ],
      // Tests filter[foo][value][]=1&filter[foo][value][]=10&
      // filter[foo][operator]=BETWEEN.
      [
        ['foo' => ['value' => ['1', '10'], 'operator' => 'BETWEEN']],
        ['foo' => ['condition' => ['path' => 'foo', 'value' => ['1', '10'], 'operator' => 'BETWEEN']]],
      ],
      // Tests filter[0][condition][path]=foo&filter[0][condition][value]=1&filter[0][condition][operator]=>.
      [
        [0 => ['condition' => ['path' => 'foo', 'value' => '1', 'operator' => '>']]],
        [0 => ['condition' => ['path' => 'foo', 'value' => '1', 'operator' => '>']]],
      ],
      // Tests filter[0][path]=foo&filter[0][value][]=bar&
      // filter[0][value][]=baz.
      [
        [0 => ['path' => 'foo', 'value' => ['bar', 'baz']]],
        [0 => ['condition' => ['path' => 'foo', 'value' => ['bar', 'baz'], 'operator' => '=']]],
      ],
      // Tests filter[0][path]=foo&filter[0][value]=bar&
      // filter[1][condition][path]=baz&filter[1][condition][value]=zab&
      // filter[1][condition][operator]=<>.
      [
        [
          0 => ['path' => 'foo', 'value' => 'bar'],
          1 => ['condition' => ['path' => 'baz', 'value' => 'zab', 'operator' => '<>']],
        ],
        [
          0 => ['condition' => ['path' => 'foo', 'value' => 'bar', 'operator' => '=']],
          1 => ['condition' => ['path' => 'baz', 'value' => 'zab', 'operator' => '<>']],
        ],
      ],
      // Tests filter[zero][path]=foo&filter[zero][value]=bar&
      // filter[one][condition][path]=baz&filter[one][condition][value]=zab&
      // filter[one][condition][operator]=<>.
      [
        [
          'zero' => ['path' => 'foo', 'value' => 'bar'],
          'one' => ['condition' => ['path' => 'baz', 'value' => 'zab', 'operator' => '<>']],
        ],
        [
          'zero' => ['condition' => ['path' => 'foo', 'value' => 'bar', 'operator' => '=']],
          'one' => ['condition' => ['path' => 'baz', 'value' => 'zab', 'operator' => '<>']],
        ],
      ],
    ];
  }

}
