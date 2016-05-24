<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The front controller for the JSON API routes.
   *
   * All routes will use this callback to bootstrap the JSON API process.
   *
   * @var string
   */
  const FRONT_CONTROLLER = '\Drupal\jsonapi\RequestHandler::handle';

  /**
   * The resource manager interface.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager.
   */
  public function __construct(ResourceManagerInterface $resource_manager) {
    $this->resourceManager = $resource_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /* @var \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager */
    $resource_manager = $container->get('jsonapi.resource.manager');
    return new static($resource_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $collection = new RouteCollection();
    foreach ($this->resourceManager->all() as $resource) {
      $global_config = $resource->getGlobalConfig();
      $prefix = $global_config->get('prefix');
      $entity_type = $resource->getEntityTypeId();
      $bundle = $resource->getBundleId();
      $partial_path = '/' . $prefix . $resource->getPath();
      $route_key = sprintf('%s.dynamic.%s.', $prefix, $resource->getTypeName());
      // Add the collection route.
      $defaults = [
        '_controller' => static::FRONT_CONTROLLER,
      ];

      // Collection endpoint, like /api/photos.
      $collection->add($route_key . 'collection', (new Route($partial_path))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $entity_type)
        ->setRequirement('_bundle', $bundle)
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setOption('serialization_class', $resource->getDeserializationTargetClass())
        ->setMethods(['GET', 'POST']));

      // Individual endpoint, like /api/photos/123.
      $parameters = [$entity_type => ['type' => 'entity:' . $entity_type]];
      $collection->add($route_key . 'individual', (new Route(sprintf('%s/{%s}', $partial_path, $entity_type)))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $entity_type)
        ->setRequirement('_bundle', $bundle)
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setOption('parameters', $parameters)
        ->setOption('serialization_class', $resource->getDeserializationTargetClass())
        ->setMethods(['GET', 'PATCH', 'DELETE']));

      // Related endpoint, like /api/photos/123/comments.
      $collection->add($route_key . 'related', (new Route(sprintf('%s/{%s}/{related}', $partial_path, $entity_type)))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $entity_type)
        ->setRequirement('_bundle', $bundle)
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setOption('parameters', $parameters)
        ->setMethods(['GET']));

      // Related endpoint, like /api/photos/123/comments.
      $collection->add($route_key . 'relationship', (new Route(sprintf('%s/{%s}/relationships/{related}', $partial_path, $entity_type)))
        ->addDefaults($defaults + ['_on_relationship' => TRUE])
        ->setRequirement('_entity_type', $entity_type)
        ->setRequirement('_bundle', $bundle)
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setOption('parameters', $parameters)
        ->setMethods(['GET', 'POST', 'DELETE']));
    }

    return $collection;
  }

}
