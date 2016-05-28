<?php

namespace Drupal\Tests\jsonapi\Unit\Context;

use Drupal\jsonapi\Context\CurrentContext;
use Drupal\jsonapi\Configuration\ResourceConfig;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\Routing\Param\Filter;
use Drupal\jsonapi\Routing\Param\Sort;
use Drupal\jsonapi\Routing\Param\CursorPage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class CurrentContextTest.
 *
 * @package \Drupal\jsonapi\Test\Unit
 *
 * @coversDefaultClass \Drupal\jsonapi\Context\CurrentContext
 * @group jsonapi
 */
class CurrentContextTest extends UnitTestCase {

  /**
   * A mock for the current route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRoute;

  /**
   * A mock for the current route.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * A mock for the entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * A request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Create a mock for the entity field manager.
    $this->fieldManager = $this->prophesize(EntityFieldManagerInterface::CLASS)->reveal();

    // Create a mock for the current route match.
    $route_prophecy = $this->prophesize(RouteMatchInterface::CLASS);
    $route_prophecy->getRouteObject()->willReturn(new Route(
      '/api/articles', [], ['_entity_type' => 'node', '_bundle' => 'article']
    ));
    $this->currentRoute = $route_prophecy->reveal();

    // Create a mock for the ResourceManager service.
    $resource_prophecy = $this->prophesize(ResourceManagerInterface::CLASS);
    $resource_config = new ResourceConfig(
      $this->prophesize(ConfigFactoryInterface::CLASS)->reveal(),
      $this->prophesize(EntityTypeManagerInterface::CLASS)->reveal()
    );
    $resource_prophecy->get('node', 'article')->willReturn(
      $resource_config
    );
    $this->resourceManager = $resource_prophecy->reveal();

    $this->requestStack = new RequestStack();
    $this->requestStack->push(new Request([], [], ['_json_api_params' => [
      'filter' => new Filter([], 'node', $this->fieldManager),
      'sort' => new Sort([]),
      'page' => new CursorPage([]),
      //'include' => new IncludeParam([]),
      //'fields' => new Fields([]),
    ]]));
  }

  /**
   * @covers ::getResourceConfig
   */
  public function testGetResourceConfig() {
    $request_context = new CurrentContext(
      $this->currentRoute, $this->resourceManager, $this->requestStack
    );

    $resource_config = $request_context->getResourceConfig();

    $this->assertEquals(
      $this->resourceManager->get('node', 'article'),
      $resource_config
    );
  }

  /**
   * @covers ::getCurrentRouteMatch
   */
  public function testGetCurrentRouteMatch() {
    $request_context = new CurrentContext(
      $this->currentRoute, $this->resourceManager, $this->requestStack
    );

    $this->assertEquals(
      $this->currentRoute,
      $request_context->getCurrentRouteMatch()
    );
  }

  /**
   * @covers ::getJsonApiParameter
   */
  public function testGetJsonApiParameter() {
    $request_context = new CurrentContext(
      $this->currentRoute, $this->resourceManager
    );

    $request_context->fromRequestStack($this->requestStack);

    $expected = new Sort([]);
    $actual = $request_context->getJsonApiParameter('sort');

    $this->assertEquals($expected, $actual);
  }

}
