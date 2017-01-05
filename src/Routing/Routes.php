<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\Resource\JsonApiDocumentTopLevel;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * @internal
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
   * @var \Drupal\jsonapi\Configuration\ResourceManager
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
    foreach ($this->resourceManager->all() as $resource_config) {
      $build_route_name = function ($key) use ($resource_config) {
        return sprintf('jsonapi.%s.%s', $resource_config->getTypeName(), $key);
      };

      $defaults = [
        RouteObjectInterface::CONTROLLER_NAME => static::FRONT_CONTROLLER,
      ];
      // Options that apply to all routes.
      $options = [
        '_auth' => $this->authProviderList(),
        '_is_jsonapi' => TRUE,
      ];

      // Collection endpoint, like /jsonapi/file/photo.
      $route_collection = (new Route('/jsonapi' . $resource_config->getPath()))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $resource_config->getEntityTypeId())
        ->setRequirement('_bundle', $resource_config->getBundleId())
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setRequirement('_custom_parameter_names', 'TRUE')
        ->setOption('serialization_class', JsonApiDocumentTopLevel::class)
        ->setMethods(['GET', 'POST']);
      $route_collection->addOptions($options);
      $collection->add($build_route_name('collection'), $route_collection);

      // Individual endpoint, like /jsonapi/file/photo/123.
      $parameters = [$resource_config->getEntityTypeId() => ['type' => 'entity:' . $resource_config->getEntityTypeId()]];
      $route_individual = (new Route('/jsonapi' . sprintf('%s/{%s}', $resource_config->getPath(), $resource_config->getEntityTypeId())))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $resource_config->getEntityTypeId())
        ->setRequirement('_bundle', $resource_config->getBundleId())
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setRequirement('_custom_parameter_names', 'TRUE')
        ->setOption('parameters', $parameters)
        ->setOption('_auth', $this->authProviderList())
        ->setOption('serialization_class', JsonApiDocumentTopLevel::class)
        ->setMethods(['GET', 'PATCH', 'DELETE']);
      $route_individual->addOptions($options);
      $collection->add($build_route_name('individual'), $route_individual);

      // Related resource, like /jsonapi/file/photo/123/comments.
      $route_related = (new Route('/jsonapi' . sprintf('%s/{%s}/{related}', $resource_config->getPath(), $resource_config->getEntityTypeId())))
        ->addDefaults($defaults)
        ->setRequirement('_entity_type', $resource_config->getEntityTypeId())
        ->setRequirement('_bundle', $resource_config->getBundleId())
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setRequirement('_custom_parameter_names', 'TRUE')
        ->setOption('parameters', $parameters)
        ->setOption('_auth', $this->authProviderList())
        ->setMethods(['GET']);
      $route_related->addOptions($options);
      $collection->add($build_route_name('related'), $route_related);

      // Related endpoint, like /jsonapi/file/photo/123/relationships/comments.
      $route_relationship = (new Route('/jsonapi' . sprintf('%s/{%s}/relationships/{related}', $resource_config->getPath(), $resource_config->getEntityTypeId())))
        ->addDefaults($defaults + ['_on_relationship' => TRUE])
        ->setRequirement('_entity_type', $resource_config->getEntityTypeId())
        ->setRequirement('_bundle', $resource_config->getBundleId())
        ->setRequirement('_permission', 'access content')
        ->setRequirement('_format', 'api_json')
        ->setRequirement('_custom_parameter_names', 'TRUE')
        ->setOption('parameters', $parameters)
        ->setOption('_auth', $this->authProviderList())
        ->setOption('serialization_class', EntityReferenceFieldItemList::class)
        ->setMethods(['GET', 'POST', 'PATCH', 'DELETE']);
      $route_relationship->addOptions($options);
      $collection->add($build_route_name('relationship'), $route_relationship);
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
