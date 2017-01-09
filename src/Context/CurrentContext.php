<?php

namespace Drupal\jsonapi\Context;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
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
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * The current JSON API resource type.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType
   */
  protected $resourceType;

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
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   The resource type repository.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(ResourceTypeRepository $resource_type_repository, RequestStack $request_stack, RouteMatchInterface $route_match) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->requestStack = $request_stack;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceType() {
    if (!isset($this->resourceType)) {
      $route = $this->routeMatch->getRouteObject();
      $entity_type_id = $route->getRequirement('_entity_type');
      $bundle = $route->getRequirement('_bundle');
      $this->resourceType = $this->resourceTypeRepository
        ->get($entity_type_id, $bundle);
    }

    return $this->resourceType;
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
