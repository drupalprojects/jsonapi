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
   * @covers ::get
   */
  public function testGetNested() {
    $original = [
      'host.nested.deep' => [
        'condition' => [
          'field' => 'host.nested.deep',
          'value' => ['bar', 'baz'],
          'operator' => '=',
        ],
      ],
    ];
    $expected = [
      'host.nested.deep' => [
        'condition' => [
          'field' => 'host.entity.nested.entity.deep',
          'value' => ['bar', 'baz'],
          'operator' => '=',
        ],
      ],
    ];
    $field_manager = $this->prophesize(EntityFieldManagerInterface::class);
    $field_storage1 = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage1->getType()->willReturn('entity_reference');
    $field_storage1->getSetting('target_type')->willReturn('ipsum');
    $field_storage2 = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage2->getType()->willReturn('entity_reference');
    $field_storage2->getSetting('target_type')->willReturn('dolor');
    $field_manager->getFieldStorageDefinitions('lorem')
      ->willReturn(['host' => $field_storage1->reveal()]);
    $field_manager->getFieldStorageDefinitions('ipsum')
      ->willReturn(['nested' => $field_storage2->reveal()]);
    $pager = new Filter(
      $original,
      'lorem', $field_manager->reveal());
    $value = $pager->get();
    $this->assertEquals($expected, $value);
  }

  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetNestedError() {
    $original = [
      'host.nested.deep' => [
        'condition' => [
          'field' => 'host.nested.deep',
          'value' => ['bar', 'baz'],
          'operator' => '=',
        ],
      ],
    ];
    $field_manager = $this->prophesize(EntityFieldManagerInterface::class);
    $field_storage1 = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage1->getType()->willReturn('entity_reference');
    $field_storage1->getSetting('target_type')->willReturn('ipsum');
    $field_storage2 = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage2->getType()->willReturn('sid');
    $field_manager->getFieldStorageDefinitions('lorem')
      ->willReturn(['host' => $field_storage1->reveal()]);
    $field_manager->getFieldStorageDefinitions('ipsum')
      ->willReturn(['nested' => $field_storage2->reveal()]);
    $pager = new Filter(
      $original,
      'lorem', $field_manager->reveal());
    $pager->get();
    $this->assertTrue(FALSE);
  }


  /**
   * @covers ::get
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function testGetNestedError2() {
    $original = [
      'host.nested.deep' => [
        'condition' => [
          'field' => 'host.nested.deep',
          'value' => ['bar', 'baz'],
          'operator' => '=',
        ],
      ],
    ];
    $field_manager = $this->prophesize(EntityFieldManagerInterface::class);
    $field_storage1 = $this->prophesize(FieldStorageDefinitionInterface::class);
    $field_storage1->getType()->willReturn('entity_reference');
    $field_storage1->getSetting('target_type')->willReturn('ipsum');
    $field_manager->getFieldStorageDefinitions('lorem')
      ->willReturn(['fail' => $field_storage1->reveal()]);
    $pager = new Filter(
      $original,
      'lorem', $field_manager->reveal());
    $pager->get();
    $this->assertTrue(FALSE);
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
