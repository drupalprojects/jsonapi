<?php

namespace Drupal\jsonapi\Context;

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
   * @var \Symfony\Component\Routing\Route
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
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager service.
   */
  public function __construct(ResourceManagerInterface $resource_manager) {
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
    $this->currentRoute = $this->currentRequest->get('_route_object');
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceConfig() {
    if (!isset($this->resourceConfig)) {
      $this->resourceConfig = $this->resourceManager->get(
        $this->currentRoute->getRequirement('_entity_type'),
        $this->currentRoute->getRequirement('_bundle')
      );
    }

    return $this->resourceConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentRoute() {
    return $this->currentRoute;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceManager() {
    return $this->resourceManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsonApiParameter($parameter_key) {
    $params = $this->currentRequest->attributes->get('_json_api_params');
    return (isset($params[$parameter_key])) ? $params[$parameter_key] : NULL;
  }

}
