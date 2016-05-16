<?php

namespace Drupal\Tests\jsonapi\Unit\Routing\Param;

use Drupal\jsonapi\Routing\Param\Sort;
use Drupal\Tests\UnitTestCase;


/**
 * Class SortTest.
 *
 * @package Drupal\jsonapi\Test\Unit
 *
 * @coversDefaultClass \Drupal\jsonapi\Routing\Param\Sort
 * @group jsonapi
 */
class SortTest extends UnitTestCase {

  /**
   * @covers ::get
   * @dataProvider getProvider
   */
  public function testGet($original, $expected) {
    $pager = new Sort($original);
    $this->assertEquals($expected, $pager->get());
  }

  /**
   * Data provider for testGet.
   */
  public function getProvider() {
    return [
      ['lorem', [['value' => 'lorem', 'direction' => 'ASC']]],
      ['-lorem', [['value' => 'lorem', 'direction' => 'DESC']]],
      ['-lorem,ipsum', [['value' => 'lorem', 'direction' => 'DESC'], ['value' => 'ipsum', 'direction' => 'ASC']]],
      ['-lorem,-ipsum', [['value' => 'lorem', 'direction' => 'DESC'], ['value' => 'ipsum', 'direction' => 'DESC']]],
    ];
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetFail() {
    $pager = new Sort(['lorem']);
    $pager->get();
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetEmpty() {
    $pager = new Sort('');
    $pager->get();
  }

}
