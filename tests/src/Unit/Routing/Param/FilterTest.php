<?php

namespace Drupal\jsonapi\Test\Unit\Routing\Param;

use Drupal\jsonapi\Routing\Param\Filter;
use Drupal\Tests\UnitTestCase;


/**
 * Class FilterTest.
 *
 * @package Drupal\jsonapi\Test\Unit
 *
 * @coversDefaultClass \Drupal\jsonapi\Routing\Param\Filter
 * @group jsonapi
 */
class FilterTest extends UnitTestCase {

  /**
   * @covers ::get
   * @dataProvider getProvider
   */
  public function testGet($original, $expected) {
    $pager = new Filter($original);
    $this->assertEquals($expected, $pager->get());
  }

  /**
   * Data provider for testGet.
   */
  public function getProvider() {
    return [
      [
        ['field1' => 'lorem'],
        ['field1' => ['value' => ['lorem'], 'operator' => ['='], 'multivalue' => FALSE]],
      ],
      [
        [
          'field1' => 'lorem',
          'field2' => ['value' => ['lorem'], 'operator' => ['<>'], 'multivalue' => FALSE],
        ],
        [
          'field1' => ['value' => ['lorem'], 'operator' => ['='], 'multivalue' => FALSE],
          'field2' => ['value' => ['lorem'], 'operator' => ['<>'], 'multivalue' => FALSE],
        ],
      ],
      [
        ['field1' => ['value' => 'lorem']],
        ['field1' => ['value' => ['lorem'], 'operator' => ['='], 'multivalue' => FALSE]],
      ],
      [
        ['field1' => ['value' => 'lorem']],
        ['field1' => ['value' => ['lorem'], 'operator' => ['='], 'multivalue' => FALSE]],
      ],
      [
        ['field1' => ['value' => ['lorem', 'ipsum']]],
        ['field1' => ['value' => ['lorem', 'ipsum'], 'operator' => ['=', '='], 'multivalue' => FALSE]],
      ],
      [
        ['field1' => ['value' => ['lorem', 'ipsum'], 'operator' => 'IN']],
        ['field1' => ['value' => ['lorem', 'ipsum'], 'operator' => ['IN'], 'multivalue' => TRUE]],
      ],
      [
        ['field1' => ['value' => ['lorem', 'ipsum'], 'operator' => ['<>']]],
        ['field1' => ['value' => ['lorem', 'ipsum'], 'operator' => ['<>', '='], 'multivalue' => FALSE]],
      ],
    ];
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetFail() {
    $pager = new Filter('lorem');
    $pager->get();
  }

}
