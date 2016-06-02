<?php

namespace Drupal\Tests\jsonapi\Unit\Routing;

use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\Routing\Routes;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RoutesTest.
 *
 * @package Drupal\Tests\jsonapi\Unit\Routing
 *
 * @coversDefaultClass \Drupal\jsonapi\Routing\Routes
 *
 * @group jsonapi
 */
class RoutesTest extends UnitTestCase {

  /**
   * List of routes objects for the different scenarios.
   *
   * @var Routes[]
   */
  protected $routes;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Mock the resource manager to have some resources available.
    $resource_manager = $this->prophesize(ResourceManagerInterface::class);

    // Create some resource mocks for the manager.
    $resource_config = $this->prophesize(ResourceConfigInterface::class);
    $global_config = $this->prophesize(ImmutableConfig::class);
    $global_config->get('prefix')->willReturn('api');
    $resource_config->getGlobalConfig()->willReturn($global_config->reveal());
    $resource_config->getEntityTypeId()->willReturn('entity_type_1');
    $resource_config->getBundleId()->willReturn('bundle_1_1');
    // Make sure that we're not coercing the bundle into the path, they can be
    // different in the future.
    $resource_config->getPath()->willReturn('/bundle_path_1');
    $resource_config->getTypeName()->willReturn('resource_type_1');
    $resource_manager->all()->willReturn([$resource_config->reveal()]);
    $container = $this->prophesize(ContainerInterface::class);
    $container->get('jsonapi.resource.manager')->willReturn($resource_manager->reveal());
    $auth_collector = $this->prophesize(AuthenticationCollectorInterface::class);
    $auth_collector->getSortedProviders()->willReturn([
      'lorem' => [],
      'ipsum' => [],
    ]);
    $container->get('authentication_collector')->willReturn($auth_collector->reveal());

    $this->routes['ok'] = Routes::create($container->reveal());
  }


  /**
   * @covers ::routes
   */
  public function testRoutesCollection() {
    // Get the route collection and start making assertions.
    $routes = $this->routes['ok']->routes();

    // Make sure that there are 4 routes for each resource.
    $this->assertEquals(4, $routes->count());

    $iterator = $routes->getIterator();
    // Check the collection route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('api.dynamic.resource_type_1.collection');
    $this->assertSame('/api/bundle_path_1', $route->getPath());
    $this->assertSame('entity_type_1', $route->getRequirement('_entity_type'));
    $this->assertSame('bundle_1_1', $route->getRequirement('_bundle'));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals(['GET', 'POST'], $route->getMethods());
    $this->assertSame('\Drupal\jsonapi\RequestHandler::handle', $route->getDefault('_controller'));
    $this->assertSame('Drupal\jsonapi\Resource\DocumentWrapperInterface', $route->getOption('serialization_class'));
  }

  /**
   * @covers ::routes
   */
  public function testRoutesIndividual() {
    // Get the route collection and start making assertions.
    $iterator = $this->routes['ok']->routes()->getIterator();

    // Check the individual route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('api.dynamic.resource_type_1.individual');
    $this->assertSame('/api/bundle_path_1/{entity_type_1}', $route->getPath());
    $this->assertSame('entity_type_1', $route->getRequirement('_entity_type'));
    $this->assertSame('bundle_1_1', $route->getRequirement('_bundle'));
    $this->assertEquals(['GET', 'PATCH', 'DELETE'], $route->getMethods());
    $this->assertSame('\Drupal\jsonapi\RequestHandler::handle', $route->getDefault('_controller'));
    $this->assertSame('Drupal\jsonapi\Resource\DocumentWrapperInterface', $route->getOption('serialization_class'));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals(['entity_type_1' => ['type' => 'entity:entity_type_1']], $route->getOption('parameters'));
  }

  /**
   * @covers ::routes
   */
  public function testRoutesRelated() {
    // Get the route collection and start making assertions.
    $iterator = $this->routes['ok']->routes()->getIterator();

    // Check the related route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('api.dynamic.resource_type_1.related');
    $this->assertSame('/api/bundle_path_1/{entity_type_1}/{related}', $route->getPath());
    $this->assertSame('entity_type_1', $route->getRequirement('_entity_type'));
    $this->assertSame('bundle_1_1', $route->getRequirement('_bundle'));
    $this->assertEquals(['GET'], $route->getMethods());
    $this->assertSame('\Drupal\jsonapi\RequestHandler::handle', $route->getDefault('_controller'));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals(['entity_type_1' => ['type' => 'entity:entity_type_1']], $route->getOption('parameters'));
  }

  /**
   * @covers ::routes
   */
  public function testRoutesRelationships() {
    // Get the route collection and start making assertions.
    $iterator = $this->routes['ok']->routes()->getIterator();

    // Check the relationships route.
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $iterator->offsetGet('api.dynamic.resource_type_1.relationship');
    $this->assertSame('/api/bundle_path_1/{entity_type_1}/relationships/{related}', $route->getPath());
    $this->assertSame('entity_type_1', $route->getRequirement('_entity_type'));
    $this->assertSame('bundle_1_1', $route->getRequirement('_bundle'));
    $this->assertEquals(['GET', 'POST', 'DELETE'], $route->getMethods());
    $this->assertSame('\Drupal\jsonapi\RequestHandler::handle', $route->getDefault('_controller'));
    $this->assertSame(['lorem', 'ipsum'], $route->getOption('_auth'));
    $this->assertEquals(['entity_type_1' => ['type' => 'entity:entity_type_1']], $route->getOption('parameters'));
    $this->assertSame('Drupal\Core\Field\EntityReferenceFieldItemList', $route->getOption('serialization_class'));
  }

}
