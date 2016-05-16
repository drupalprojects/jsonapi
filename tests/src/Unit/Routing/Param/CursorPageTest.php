<?php

namespace Drupal\jsonapi\Test\Unit\Routing\Param;

use Drupal\jsonapi\Routing\Param\CursorPage;
use Drupal\Tests\UnitTestCase;


/**
 * Class CursorPageTest.
 *
 * @package Drupal\jsonapi\Test\Unit
 *
 * @coversDefaultClass \Drupal\jsonapi\Routing\Param\CursorPage
 * @group jsonapi
 */
class CursorPageTest extends UnitTestCase {

  /**
   * @covers ::get
   * @dataProvider getProvider
   */
  public function testGet($original, $max_page, $expected) {
    $pager = new CursorPage($original, $max_page);
    $this->assertEquals($expected, $pager->get());
  }

  /**
   * Data provider for testGet.
   */
  public function getProvider() {
    return [
      [['cursor' => 12, 'size' => 20], 50, ['cursor' => 12, 'size' => 20]],
      [['cursor' => 12, 'size' => 60], 50, ['cursor' => 12, 'size' => 50]],
      [['cursor' => 12], 50, ['cursor' => 12, 'size' => 50]],
      [['cursor' => 0], 50, ['cursor' => 0, 'size' => 50]],
      [[], 50, ['size' => 50]],
    ];
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetFail() {
    $pager = new CursorPage('lorem');
    $pager->get();
  }

}
