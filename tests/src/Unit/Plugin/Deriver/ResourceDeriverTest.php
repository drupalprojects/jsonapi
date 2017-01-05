<?php

namespace Drupal\Tests\jsonapi\Unit\Plugin\Deriver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\Configuration\ResourceConfig;
use Drupal\jsonapi\Plugin\Deriver\ResourceDeriver;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ResourceDeriverTest.
 *
 * @package Drupal\Tests\jsonapi\Unit\Plugin\Deriver
 *
 * @coversDefaultClass \Drupal\jsonapi\Plugin\Deriver\ResourceDeriver
 *
 * @group jsonapi
 */
class ResourceDeriverTest extends UnitTestCase {

  /**
   * The deriver under test.
   *
   * @var ResourceDeriver
   */
  protected $deriver;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Mock the resource manager to have some resources available.
    $resource_manager = $this->prophesize(ResourceManagerInterface::class);

    // Create some resource mocks for the manager.
    $resource_manager->all()->willReturn([new ResourceConfig('entity_type_1', 'bundle_1_1', EntityInterface::class)]);
    $resource_manager->hasBundle(Argument::type('string'))->willReturn(FALSE);

    $container = $this->prophesize(ContainerInterface::class);
    $container->get('jsonapi.resource.manager')->willReturn($resource_manager->reveal());
    $this->deriver = ResourceDeriver::create($container->reveal(), 'bundle');
  }


  /**
   * @covers ::getDerivativeDefinitions
   */
  public function testGetDerivativeDefinitions() {
    $expected = ['jsonapi.entity_type_1--bundle_1_1' => [
      'id' => 'jsonapi.entity_type_1--bundle_1_1',
      'entityType' => 'entity_type_1',
      'bundle' => 'bundle_1_1',
      'hasBundle' => FALSE,
      'type' => 'entity_type_1--bundle_1_1',
      'data' => [
        'partialPath' => '/entity_type_1/bundle_1_1',
      ],
      'permission' => 'access content',
      'controller' => '\\Drupal\\jsonapi\\RequestHandler::handle',
    ]];
    $actual = $this->deriver->getDerivativeDefinitions([
      'permission' => 'access content',
      'controller' => '\Drupal\jsonapi\RequestHandler::handle',
    ]);
    $this->assertArrayEquals($expected, $actual);
  }

}
