<?php

namespace Drupal\jsonapi\Context;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class CurrentContext.
 *
 * Service for accessing information about the current JSON API request.
 *
 * @package \Drupal\jsonapi\Context
 *
 * @internal
 */
class CurrentContext implements CurrentContextInterface {

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
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Creates a CurrentContext object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(ResourceManagerInterface $resource_manager, RequestStack $request_stack, RouteMatchInterface $route_match) {
    $this->resourceManager = $resource_manager;
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceConfig() {
    if (!isset($this->resourceConfig)) {
      $route = $this->routeMatch->getRouteObject();
      $entity_type_id = $route->getRequirement('_entity_type');
      $bundle_id = $route->getRequirement('_bundle');
      $this->resourceConfig = $this->resourceManager
        ->get($entity_type_id, $bundle_id);
    }

    return $this->resourceConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function isOnRelationship() {
    return (bool) $this->routeMatch
      ->getRouteObject()
      ->getDefault('_on_relationship');
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
    $params = $this
      ->requestStack
      ->getCurrentRequest()
      ->attributes
      ->get('_json_api_params');
    return (isset($params[$parameter_key])) ? $params[$parameter_key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExtension($extension_name) {
    return in_array($extension_name, $this->getExtensions());
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensions() {
    $content_type_header = $this
      ->requestStack
      ->getCurrentRequest()
      ->headers
      ->get('Content-Type');
    if (preg_match('/ext="([^"]+)"/i', $content_type_header, $match)) {
      $extensions = array_map('trim', explode(',', $match[1]));
      return $extensions;
    }
    return [];
  }

}
