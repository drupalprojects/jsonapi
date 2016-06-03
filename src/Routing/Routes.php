<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\Resource\DocumentWrapperInterface;
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
   * The authentication collector.
   *
   * @var \Drupal\Core\Authentication\AuthenticationCollectorInterface
   */
  protected $authCollector;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager.
   * @param \Drupal\Core\Authentication\AuthenticationCollectorInterface $auth_collector
   *   The resource manager.
   */
  public function __construct(ResourceManagerInterface $resource_manager, AuthenticationCollectorInterface $auth_collector) {
    $this->resourceManager = $resource_manager;
    $this->authCollector = $auth_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /* @var \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager */
    $resource_manager = $container->get('jsonapi.resource.manager');
    /* @var \Drupal\Core\Authentication\AuthenticationCollectorInterface $auth_collector */
    $auth_collector = $container->get('authentication_collector');
    return new static($resource_manager, $auth_collector);
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
        ->setOption('_auth', $this->authProviderList())
        ->setOption('serialization_class', DocumentWrapperInterface::class)
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
        ->setOption('_auth', $this->authProviderList())
        ->setOption('serialization_class', DocumentWrapperInterface::class)
        ->setMethods(['GET', 'PATCH', 'DELETE']));

      // Related endpoint, like /api/photos/123/comments.
      $collection->add($route_key . 'related', (new Route(sprintf('%s/{%s}/{related}', $partial_path, $entity_type)))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $entity_type)
        ->setRequirement('_bundle', $bundle)
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setOption('parameters', $parameters)
        ->setOption('_auth', $this->authProviderList())
        ->setMethods(['GET']));

      // Related endpoint, like /api/photos/123/relationships/comments.
      $collection->add($route_key . 'relationship', (new Route(sprintf('%s/{%s}/relationships/{related}', $partial_path, $entity_type)))
        ->addDefaults($defaults + ['_on_relationship' => TRUE])
        ->setRequirement('_entity_type', $entity_type)
        ->setRequirement('_bundle', $bundle)
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setOption('parameters', $parameters)
        ->setOption('_auth', $this->authProviderList())
        ->setOption('serialization_class', EntityReferenceFieldItemList::class)
        ->setMethods(['GET', 'POST', 'PATCH', 'DELETE']));
    }

    return $collection;
  }

  /**
   * Build a list of authentication provider ids.
   *
   * @return string[]
   *   The list of IDs.
   */
  protected function authProviderList() {
    if (isset($this->providerIds)) {
      return $this->providerIds;
    }
    $this->providerIds = array_keys($this->authCollector->getSortedProviders());
    return $this->providerIds;
  }

}
