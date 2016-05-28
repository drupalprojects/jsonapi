<?php

namespace Drupal\jsonapi\Context;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CurrentContext.
 *
 * Service for accessing information about the current JSON API request.
 *
 * @package \Drupal\jsonapi\Context
 */
class CurrentContext implements CurrentContextInterface {

  /**
   * The current route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRoute;

  /**
   * The resource manager.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * The current resource config.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceConfigInterface
   */
  protected $resourceConfig;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Creates a CurrentContext object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route
   *   The current route match.
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager service.
   */
  public function __construct(RouteMatchInterface $current_route, ResourceManagerInterface $resource_manager) {
    $this->currentRoute = $current_route;
    $this->resourceManager = $resource_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function fromRequest(Request $request) {
    $this->currentRequest = $request;
  }

  /**
   * {@inheritdoc}
   */
  public function fromRequestStack(RequestStack $request_stack) {
    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceConfig() {
    if (!isset($this->resourceConfig)) {
      $route_object = $this->currentRoute->getRouteObject();

      $this->resourceConfig = $this->resourceManager->get(
        $route_object->getRequirement('_entity_type'),
        $route_object->getRequirement('_bundle')
      );
    }

    return $this->resourceConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentRouteMatch() {
    return $this->currentRoute;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiParameter($parameter_key) {
    $params = $this->currentRequest->attributes->get('_json_api_params');
    return (isset($params[$parameter_key])) ? $params[$parameter_key] : NULL;
  }

}
