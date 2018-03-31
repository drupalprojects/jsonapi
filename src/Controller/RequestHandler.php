<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Acts as intermediate request forwarder for resource plugins.
 *
 * @internal
 */
class RequestHandler {

  /**
   * The JSON API serializer.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The field type manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The JSON API link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  protected static $requiredCacheContexts = ['user.permissions'];

  /**
   * Creates a new RequestHandler instance.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The JSON API serializer.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type manager.
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The JSON API link manager.
   */
  public function __construct(SerializerInterface $serializer, RendererInterface $renderer, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, FieldTypePluginManagerInterface $field_type_manager, LinkManager $link_manager) {
    $this->serializer = $serializer;
    $this->renderer = $renderer;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->fieldTypeManager = $field_type_manager;
    $this->linkManager = $link_manager;
  }

  /**
   * Handles a web API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response object.
   */
  public function handle(Request $request, ResourceType $resource_type) {
    $method = strtolower($request->getMethod());

    // Deserialize incoming data if available.
    $unserialized = $this->deserializeBody($request, $resource_type);
    if ($unserialized instanceof Response && !$unserialized->isSuccessful()) {
      return $unserialized;
    }

    // Determine the request parameters that should be passed to the resource
    // plugin.
    $parameters = [];

    $entity_type_id = $resource_type->getEntityTypeId();
    if ($entity = $request->get($entity_type_id)) {
      $parameters[$entity_type_id] = $entity;
    }

    if ($related = $request->get('related')) {
      $parameters['related'] = $related;
    }

    // Invoke the operation on the resource plugin.
    $action = $this->action($request, $resource_type, $method);
    $resource = $this->resourceFactory($resource_type);

    // Only add the unserialized data if there is something there.
    $extra_parameters = $unserialized ? [$unserialized, $request] : [$request];

    // Execute the request in context so the cacheable metadata from the entity
    // grants system is caught and added to the response. This is surfaced when
    // executing the underlying entity query.
    $context = new RenderContext();
    $response = $this->renderer
      ->executeInRenderContext($context, function () use ($resource, $action, $parameters, $extra_parameters) {
        return call_user_func_array([$resource, $action], array_merge($parameters, $extra_parameters));
      });
    $response->getCacheableMetadata()->addCacheContexts(static::$requiredCacheContexts);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    return $response;
  }

  /**
   * Deserializes the sent data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return mixed
   *   The deserialized data or a Response object in case of error.
   */
  public function deserializeBody(Request $request, ResourceType $resource_type) {
    $received = $request->getContent();
    if (empty($received) || $request->isMethodCacheable()) {
      return NULL;
    }
    $field_related = $resource_type->getInternalName($request->get('related'));
    try {
      return $this->serializer->deserialize($received, $request->get('serialization_class'), 'api_json', [
        'related' => $field_related,
        'target_entity' => $request->get($resource_type->getEntityTypeId()),
        'resource_type' => $resource_type,
      ]);
    }
    catch (UnexpectedValueException $e) {
      throw new UnprocessableEntityHttpException(
        sprintf('There was an error un-serializing the data. Message: %s', $e->getMessage()),
        $e
      );
    }
  }

  /**
   * Gets the method to execute in the entity resource.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being handled.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   * @param string $method
   *   The lowercase HTTP method.
   *
   * @return string
   *   The method to execute in the EntityResource.
   */
  protected function action(Request $request, ResourceType $resource_type, $method) {
    $on_relationship = (bool) $request->get('_on_relationship');
    switch ($method) {
      case 'head':
      case 'get':
        if ($on_relationship) {
          return 'getRelationship';
        }
        elseif ($request->get('related')) {
          return 'getRelated';
        }
        return $request->get($resource_type->getEntityTypeId()) ? 'getIndividual' : 'getCollection';

      case 'post':
        return ($on_relationship) ? 'createRelationship' : 'createIndividual';

      case 'patch':
        return ($on_relationship) ? 'patchRelationship' : 'patchIndividual';

      case 'delete':
        return ($on_relationship) ? 'deleteRelationship' : 'deleteIndividual';
    }
  }

  /**
   * Get the resource.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return \Drupal\jsonapi\Controller\EntityResource
   *   The instantiated resource.
   */
  protected function resourceFactory(ResourceType $resource_type) {
    $resource = new EntityResource(
      $resource_type,
      $this->entityTypeManager,
      $this->fieldManager,
      $this->fieldTypeManager,
      $this->linkManager,
      $this->resourceTypeRepository
    );
    return $resource;
  }

}
