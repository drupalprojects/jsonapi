<?php

namespace Drupal\Tests\jsonapi\Unit\Plugin;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\jsonapi\Plugin\FileDownloadUrl;
use Drupal\Tests\UnitTestCase;

/**
 * Class FileDownloadUrlTest.
 *
 * @package Drupal\Tests\jsonapi\Unit\Plugin
 *
 * @coversDefaultClass \Drupal\jsonapi\Plugin\FileDownloadUrl
 *
 * @group jsonapi
 */
class FileDownloadUrlTest extends UnitTestCase  {

  /**
   * The data definition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface
   */
  protected $definition;

  /**
   * The parent typed data.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface
   */
  protected $parent;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->definition = $this->getMock(DataDefinitionInterface::class);
    $this->parent = $this->getMock(TypedDataInterface::class);
  }

  /**
   * @covers ::compute
   */
  public function testCompute() {
    $field_list_mock = $this->getMockBuilder(FileDownloadUrl::class)
      ->setMethods(['getUris', 'fileCreateRootRelativeUrl'])
      ->disableOriginalConstructor()
      ->getMock();

    $field_list_mock
      ->expects($this->once())
      ->method('getUris')
      ->will($this->returnValue([['value' => 'public://url.to.file']]));

    $field_list_mock
      ->expects($this->once())
      ->method('fileCreateRootRelativeUrl')
      ->with($this->equalTo('public://url.to.file'))
      ->willReturn('/url.to.file');

    $this->assertArrayEquals([['value' => '/url.to.file']], $field_list_mock->compute());
  }

}
