<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Drupal\jsonapi\LabelOnlyEntity;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\JsonApiResource\EntityCollection;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Process all entity requests.
 *
 * @see \Drupal\jsonapi\Controller\RequestHandler
 * @internal
 */
class EntityResource {

  /**
   * The JSON API resource type.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType
   */
  protected $resourceType;

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
   * The current context service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The link manager service.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Instantiates a EntityResource object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity type field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager
   *   The plugin manager for fields.
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager service.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The link manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ResourceType $resource_type, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, FieldTypePluginManagerInterface $plugin_manager, LinkManager $link_manager, ResourceTypeRepositoryInterface $resource_type_repository, RendererInterface $renderer) {
    $this->resourceType = $resource_type;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->pluginManager = $plugin_manager;
    $this->linkManager = $link_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->renderer = $renderer;
  }

  /**
   * Gets the individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $response_code
   *   The response code. Defaults to 200.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
   *   Thrown when access to the entity is not allowed.
   */
  public function getIndividual(EntityInterface $entity, Request $request, $response_code = 200) {
    $entity_access = $entity->access('view', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      $entity_label_access = $entity->access('view label', NULL, TRUE);
      if (!$entity_label_access->isAllowed()) {
        throw new EntityAccessDeniedHttpException($entity, $entity_access, '/data', 'The current user is not allowed to GET the selected resource.');
      }
      $entity = new LabelOnlyEntity($entity);
    }
    $response = $this->buildWrappedResponse($entity, $response_code);
    $response->addCacheableDependency($entity_access);
    if (isset($entity_label_access)) {
      $response->addCacheableDependency($entity_label_access);
    }
    return $response;
  }

  /**
   * Verifies that the whole entity does not violate any validation constraints.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string[] $field_names
   *   (optional) An array of field names. If specified, filters the violations
   *   list to include only this set of fields. Defaults to NULL,
   *   which means that all violations will be reported.
   *
   * @throws \Drupal\jsonapi\Exception\UnprocessableHttpEntityException
   *   Thrown when violations remain after filtering.
   *
   * @see \Drupal\rest\Plugin\rest\resource\EntityResourceValidationTrait::validate()
   */
  protected function validate(EntityInterface $entity, array $field_names = NULL) {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $violations = $entity->validate();

    // Remove violations of inaccessible fields as they cannot stem from our
    // changes.
    $violations->filterByFieldAccess();

    // Filter violations based on the given fields.
    if ($field_names !== NULL) {
      $violations->filterByFields(
        array_diff(array_keys($entity->getFieldDefinitions()), $field_names)
      );
    }

    if (count($violations) > 0) {
      // Instead of returning a generic 400 response we use the more specific
      // 422 Unprocessable Entity code from RFC 4918. That way clients can
      // distinguish between general syntax errors in bad serializations (code
      // 400) and semantic errors in well-formed requests (code 422).
      // @see \Drupal\jsonapi\Normalizer\UnprocessableHttpEntityExceptionNormalizer
      $exception = new UnprocessableHttpEntityException();
      $exception->setViolations($violations);
      throw $exception;
    }
  }

  /**
   * Creates an individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
   *   Thrown when the entity already exists.
   */
  public function createIndividual(EntityInterface $entity, Request $request) {
    if ($entity instanceof FieldableEntityInterface) {
      // Only check 'edit' permissions for fields that were actually submitted
      // by the user. Field access makes no distinction between 'create' and
      // 'update', so the 'edit' operation is used here.
      $document = Json::decode($request->getContent());
      if (isset($document['data']['attributes'])) {
        $received_attributes = array_keys($document['data']['attributes']);
        foreach ($received_attributes as $field_name) {
          $internal_field_name = $this->resourceType->getInternalName($field_name);
          $field_access = $entity->get($internal_field_name)
            ->access('edit', NULL, TRUE);
          if (!$field_access->isAllowed()) {
            throw new EntityAccessDeniedHttpException(NULL, $field_access, '/data/attributes/' . $field_name, sprintf('The current user is not allowed to POST the selected field (%s).', $field_name));
          }
        }
      }
      if (isset($document['data']['relationships'])) {
        $received_relationships = array_keys($document['data']['relationships']);
        foreach ($received_relationships as $field_name) {
          $internal_field_name = $this->resourceType->getInternalName($field_name);
          $field_access = $entity->get($internal_field_name)->access('edit', NULL, TRUE);
          if (!$field_access->isAllowed()) {
            throw new EntityAccessDeniedHttpException(NULL, $field_access, '/data/relationships/' . $field_name, sprintf('The current user is not allowed to POST the selected field (%s).', $field_name));
          }
        }
      }
    }

    $this->validate($entity);

    // Return a 409 Conflict response in accordance with the JSON API spec. See
    // http://jsonapi.org/format/#crud-creating-responses-409.
    if ($this->entityExists($entity)) {
      throw new ConflictHttpException('Conflict: Entity already exists.');
    }

    $entity->save();

    // Build response object.
    $response = $this->buildWrappedResponse($entity, 201);

    // According to JSON API specification, when a new entity was created
    // we should send "Location" header to the frontend.
    $entity_url = $this->linkManager->getEntityLink(
      $entity->uuid(),
      $this->resourceType,
      [],
      'individual'
    );
    if ($entity_url) {
      $response->headers->set('Location', $entity_url);
    }

    // Return response object with updated headers info.
    return $response;
  }

  /**
   * Patches an individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Drupal\Core\Entity\EntityInterface $parsed_entity
   *   The entity with the new data.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the selected entity does not match the id in th payload.
   */
  public function patchIndividual(EntityInterface $entity, EntityInterface $parsed_entity, Request $request) {
    $body = Json::decode($request->getContent());
    $data = $body['data'];
    if ($data['id'] != $entity->uuid()) {
      throw new BadRequestHttpException(sprintf(
        'The selected entity (%s) does not match the ID in the payload (%s).',
        $entity->uuid(),
        $data['id']
      ));
    }
    $data += ['attributes' => [], 'relationships' => []];
    $field_names = array_merge(array_keys($data['attributes']), array_keys($data['relationships']));

    array_reduce($field_names, function (EntityInterface $destination, $field_name) use ($parsed_entity) {
      $this->updateEntityField($parsed_entity, $destination, $field_name);
      return $destination;
    }, $entity);

    $this->validate($entity, $field_names);
    $entity->save();
    return $this->buildWrappedResponse($entity);
  }

  /**
   * Deletes an individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function deleteIndividual(EntityInterface $entity, Request $request) {
    $entity->delete();
    return new ResourceResponse(NULL, 204);
  }

  /**
   * Gets the collection of entities.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when filtering on a config entity which does not support it.
   */
  public function getCollection(Request $request) {
    // Instantiate the query for the filtering.
    $entity_type_id = $this->resourceType->getEntityTypeId();

    $route_params = $request->attributes->get('_route_params');
    $params = isset($route_params['_json_api_params']) ? $route_params['_json_api_params'] : [];
    $query = $this->getCollectionQuery($entity_type_id, $params);
    $query_cacheability = new CacheableMetadata();

    try {
      // Execute the query in a render context, to catch bubbled cacheability.
      // @see node_query_node_access_alter()
      // @see https://www.drupal.org/project/drupal/issues/2557815
      // @see https://www.drupal.org/project/drupal/issues/2794385
      // @todo Remove this when the query sytems's return value is able to carry
      // cacheability.
      $context = new RenderContext();
      $results = $this->renderer->executeInRenderContext($context, function () use ($query) {
        return $query->execute();
      });
      if (!$context->isEmpty()) {
        $query_cacheability->addCacheableDependency($context->pop());
      }
    }
    catch (\LogicException $e) {
      // Ensure good DX when an entity query involves a config entity type.
      // @todo Core should throw a better exception.
      if (strpos($e->getMessage(), 'Getting the base fields is not supported for entity type') === 0) {
        preg_match('/entity type (.*)\./', $e->getMessage(), $matches);
        $config_entity_type_id = $matches[1];
        throw new BadRequestHttpException(sprintf("Filtering on config entities is not supported by Drupal's entity API. You tried to filter on a %s config entity.", $config_entity_type_id));
      }
      else {
        throw $e;
      }
    }

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    // We request N+1 items to find out if there is a next page for the pager.
    // We may need to remove that extra item before loading the entities.
    $pager_size = $query->getMetaData('pager_size');
    if ($has_next_page = $pager_size < count($results)) {
      // Drop the last result.
      array_pop($results);
    }
    // Each item of the collection data contains an array with 'entity' and
    // 'access' elements.
    $collection_data = $this->loadEntitiesWithAccess($storage, $results);
    $entity_collection = new EntityCollection(array_column($collection_data, 'entity'));
    $entity_collection->setHasNextPage($has_next_page);

    // Calculate all the results and pass them to the EntityCollectionInterface.
    if ($this->resourceType->includeCount()) {
      $total_results = $this
        ->getCollectionCountQuery($entity_type_id, $params)
        ->execute();

      $entity_collection->setTotalCount($total_results);
    }

    $response = $this->respondWithCollection($entity_collection, $entity_type_id);

    $response->addCacheableDependency($query_cacheability);
    // Add cacheable metadata for the access result.
    $access_info = array_column($collection_data, 'access');
    array_walk($access_info, function ($access) use ($response) {
      $response->addCacheableDependency($access);
    });

    return $response;
  }

  /**
   * Gets the related resource.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function getRelated(FieldableEntityInterface $entity, $related_field, Request $request) {
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->get($this->resourceType->getInternalName($related_field));
    // Add the cacheable metadata from the host entity.
    $cacheable_metadata = CacheableMetadata::createFromObject($entity);
    $is_multiple = $field_list
      ->getDataDefinition()
      ->getFieldStorageDefinition()
      ->isMultiple();
    if (!$is_multiple && $field_list->entity) {
      $response = $this->getIndividual($field_list->entity, $request);
      // Add cacheable metadata for host entity to individual response.
      $response->addCacheableDependency($cacheable_metadata);
      return $response;
    }
    $collection_data = [];
    // Remove the entities pointing to a resource that may be disabled. Even
    // though the normalizer skips disabled references, we can avoid unnecessary
    // work by checking here too.
    /* @var \Drupal\Core\Entity\EntityInterface[] $referenced_entities */
    $referenced_entities = array_filter(
      $field_list->referencedEntities(),
      function (EntityInterface $entity) {
        return (bool) $this->resourceTypeRepository->get(
          $entity->getEntityTypeId(),
          $entity->bundle()
        );
      }
    );
    foreach ($referenced_entities as $referenced_entity) {
      $collection_data[$referenced_entity->id()] = static::getEntityAndAccess($referenced_entity);
      $cacheable_metadata->addCacheableDependency($referenced_entity);
    }
    $entity_collection = new EntityCollection(array_column($collection_data, 'entity'));
    $response = $this->buildWrappedResponse($entity_collection);

    $access_info = array_column($collection_data, 'access');
    array_walk($access_info, function ($access) use ($response) {
      $response->addCacheableDependency($access);
    });
    // $response does not contain the entity list cache tag. We add the
    // cacheable metadata for the finite list of entities in the relationship.
    $response->addCacheableDependency($cacheable_metadata);

    return $response;
  }

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param int $response_code
   *   The response code. Defaults to 200.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function getRelationship(FieldableEntityInterface $entity, $related_field, Request $request, $response_code = 200) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field_list */
    $field_list = $entity->get($this->resourceType->getInternalName($related_field));
    $response = $this->buildWrappedResponse($field_list, $response_code);
    return $response;
  }

  /**
   * Adds a relationship to a to-many relationship.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   * @param mixed $parsed_field_list
   *   The entity reference field list of items to add, or a response object in
   *   case of error.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
   *   Thrown when the current user is not allowed to PATCH the selected
   *   field(s).
   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
   *   Thrown when POSTing to a "to-one" relationship.
   */
  public function createRelationship(EntityInterface $entity, $related_field, $parsed_field_list, Request $request) {
    $related_field = $this->resourceType->getInternalName($related_field);
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list */
    if ($parsed_field_list instanceof Response) {
      // This usually means that there was an error, so there is no point on
      // processing further.
      return $parsed_field_list;
    }
    // According to the specification, you are only allowed to POST to a
    // relationship if it is a to-many relationship.
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->{$related_field};
    $is_multiple = $field_list->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->isMultiple();
    if (!$is_multiple) {
      throw new ConflictHttpException(sprintf('You can only POST to to-many relationships. %s is a to-one relationship.', $related_field));
    }

    $original_field_list = clone $field_list;
    // Time to save the relationship.
    foreach ($parsed_field_list as $field_item) {
      $field_list->appendItem($field_item->getValue());
    }
    $this->validate($entity);
    $entity->save();
    $status = static::relationshipArityIsAffected($original_field_list, $field_list)
      ? 200
      : 204;
    return $this->getRelationship($entity, $related_field, $request, $status);
  }

  /**
   * Checks whether relationship arity is affected.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $old
   *   The old (stored) entity references.
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $new
   *   The new (updated) entity references.
   *
   * @return bool
   *   Whether entities already being referenced now have additional references.
   *
   * @see \Drupal\jsonapi\Normalizer\Value\RelationshipNormalizerValue::ensureUniqueResourceIdentifierObjects()
   */
  protected static function relationshipArityIsAffected(EntityReferenceFieldItemListInterface $old, EntityReferenceFieldItemListInterface $new) {
    $old_targets = static::toTargets($old);
    $new_targets = static::toTargets($new);
    $relationship_count_changed = count($old_targets) !== count($new_targets);
    $existing_relationships_updated = !empty(array_unique(array_intersect($old_targets, $new_targets)));
    return $relationship_count_changed && $existing_relationships_updated;
  }

  /**
   * Maps a list of entity reference field objects to a list of targets.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $relationship_list
   *   A list of entity reference field objects.
   *
   * @return string[]|int[]
   *   A list of targets.
   */
  protected static function toTargets(EntityReferenceFieldItemListInterface $relationship_list) {
    $main_property_name = $relationship_list->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getMainPropertyName();

    $values = [];
    foreach ($relationship_list->getIterator() as $relationship) {
      $values[] = $relationship->getValue()[$main_property_name];
    }
    return $values;
  }

  /**
   * Updates the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   * @param mixed $parsed_field_list
   *   The entity reference field list of items to add, or a response object in
   *   case of error.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function patchRelationship(EntityInterface $entity, $related_field, $parsed_field_list, Request $request) {
    $related_field = $this->resourceType->getInternalName($related_field);
    if ($parsed_field_list instanceof Response) {
      // This usually means that there was an error, so there is no point on
      // processing further.
      return $parsed_field_list;
    }
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list */
    // According to the specification, PATCH works a little bit different if the
    // relationship is to-one or to-many.
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->{$related_field};
    $is_multiple = $field_list->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->isMultiple();
    $method = $is_multiple ? 'doPatchMultipleRelationship' : 'doPatchIndividualRelationship';
    $this->{$method}($entity, $parsed_field_list);
    $this->validate($entity);
    $entity->save();
    return $this->getRelationship($entity, $related_field, $request, 204);
  }

  /**
   * Update a to-one relationship.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list
   *   The entity reference field list of items to add, or a response object in
   *   case of error.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when a "to-one" relationship is not provided.
   */
  protected function doPatchIndividualRelationship(EntityInterface $entity, EntityReferenceFieldItemListInterface $parsed_field_list) {
    if ($parsed_field_list->count() > 1) {
      throw new BadRequestHttpException(sprintf('Provide a single relationship so to-one relationship fields (%s).', $parsed_field_list->getName()));
    }
    $this->doPatchMultipleRelationship($entity, $parsed_field_list);
  }

  /**
   * Update a to-many relationship.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list
   *   The entity reference field list of items to add, or a response object in
   *   case of error.
   *
   * @throws \Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
   *   Thrown when the current user is not allow to PATCH the selected field.
   */
  protected function doPatchMultipleRelationship(EntityInterface $entity, EntityReferenceFieldItemListInterface $parsed_field_list) {
    $field_name = $parsed_field_list->getName();
    $field_access = $parsed_field_list->access('edit', NULL, TRUE);
    if (!$field_access->isAllowed()) {
      throw new EntityAccessDeniedHttpException($entity, $field_access, '/data/relationships/' . $field_name, sprintf('The current user is not allowed to PATCH the selected field (%s).', $field_name));
    }
    $entity->{$field_name} = $parsed_field_list;
  }

  /**
   * Deletes the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   * @param mixed $parsed_field_list
   *   The entity reference field list of items to add, or a response object in
   *   case of error.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when not body was provided for the DELETE operation.
   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
   *   Thrown when deleting a "to-one" relationship.
   */
  public function deleteRelationship(EntityInterface $entity, $related_field, $parsed_field_list, Request $request = NULL) {
    if ($parsed_field_list instanceof Response) {
      // This usually means that there was an error, so there is no point on
      // processing further.
      return $parsed_field_list;
    }
    if ($parsed_field_list instanceof Request) {
      // This usually means that there was not body provided.
      throw new BadRequestHttpException(sprintf('You need to provide a body for DELETE operations on a relationship (%s).', $related_field));
    }
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field_list */
    $field_list = $entity->{$related_field};
    $is_multiple = $field_list->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->isMultiple();
    if (!$is_multiple) {
      throw new ConflictHttpException(sprintf('You can only DELETE from to-many relationships. %s is a to-one relationship.', $related_field));
    }

    // Compute the list of current values and remove the ones in the payload.
    $current_values = $field_list->getValue();
    $deleted_values = $parsed_field_list->getValue();
    $keep_values = array_udiff($current_values, $deleted_values, function ($first, $second) {
      return reset($first) - reset($second);
    });
    // Replace the existing field with one containing the relationships to keep.
    $entity->{$related_field} = $this->pluginManager
      ->createFieldItemList($entity, $related_field, $keep_values);

    // Save the entity and return the response object.
    $this->validate($entity);
    $entity->save();
    return $this->getRelationship($entity, $related_field, $request, 204);
  }

  /**
   * Gets a basic query for a collection.
   *
   * @param string $entity_type_id
   *   The entity type for the entity query.
   * @param array $params
   *   The parameters for the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A new query.
   */
  protected function getCollectionQuery($entity_type_id, array $params) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

    $query = $entity_storage->getQuery();

    // Ensure that access checking is performed on the query.
    $query->accessCheck(TRUE);

    // Compute and apply an entity query condition from the filter parameter.
    if (isset($params[Filter::KEY_NAME]) && $filter = $params[Filter::KEY_NAME]) {
      $query->condition($filter->queryCondition($query));
    }

    // Apply any sorts to the entity query.
    if (isset($params[Sort::KEY_NAME]) && $sort = $params[Sort::KEY_NAME]) {
      foreach ($sort->fields() as $field) {
        $path = $field[Sort::PATH_KEY];
        $direction = isset($field[Sort::DIRECTION_KEY]) ? $field[Sort::DIRECTION_KEY] : 'ASC';
        $langcode = isset($field[Sort::LANGUAGE_KEY]) ? $field[Sort::LANGUAGE_KEY] : NULL;
        $query->sort($path, $direction, $langcode);
      }
    }

    // Apply any pagination options to the query.
    if (isset($params[OffsetPage::KEY_NAME])) {
      $pagination = $params[OffsetPage::KEY_NAME];
    }
    else {
      $pagination = new OffsetPage(OffsetPage::DEFAULT_OFFSET, OffsetPage::SIZE_MAX);
    }
    // Add one extra element to the page to see if there are more pages needed.
    $query->range($pagination->getOffset(), $pagination->getSize() + 1);
    $query->addMetaData('pager_size', (int) $pagination->getSize());

    // Limit this query to the bundle type for this resource.
    $bundle = $this->resourceType->getBundle();
    if ($bundle && ($bundle_key = $entity_type->getKey('bundle'))) {
      $query->condition(
        $bundle_key, $bundle
      );
    }

    return $query;
  }

  /**
   * Gets a basic query for a collection count.
   *
   * @param string $entity_type_id
   *   The entity type for the entity query.
   * @param array $params
   *   The parameters for the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A new query.
   */
  protected function getCollectionCountQuery($entity_type_id, array $params) {
    // Reset the range to get all the available results.
    return $this->getCollectionQuery($entity_type_id, $params)->range()->count();
  }

  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param mixed $data
   *   The data to wrap.
   * @param int $response_code
   *   The response code.
   * @param array $headers
   *   An array of response headers.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse($data, $response_code = 200, array $headers = []) {
    return new ResourceResponse(new JsonApiDocumentTopLevel($data), $response_code, $headers);
  }

  /**
   * Respond with an entity collection.
   *
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $entity_collection
   *   The collection of entites.
   * @param string $entity_type_id
   *   The entity type.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  protected function respondWithCollection(EntityCollection $entity_collection, $entity_type_id) {
    $response = $this->buildWrappedResponse($entity_collection);

    // When a new change to any entity in the resource happens, we cannot ensure
    // the validity of this cached list. Add the list tag to deal with that.
    $list_tag = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getListCacheTags();
    $response->getCacheableMetadata()->addCacheTags($list_tag);
    return $response;
  }

  /**
   * Takes a field from the origin entity and puts it to the destination entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $origin
   *   The entity that contains the field values.
   * @param \Drupal\Core\Entity\EntityInterface $destination
   *   The entity that needs to be updated.
   * @param string $field_name
   *   The name of the field to extract and update.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when the serialized and destination entities are of different
   *   types.
   */
  protected function updateEntityField(EntityInterface $origin, EntityInterface $destination, $field_name) {
    // The update is different for configuration entities and content entities.
    if ($origin instanceof ContentEntityInterface && $destination instanceof ContentEntityInterface) {
      // First scenario: both are content entities.
      $field_name = $this->resourceType->getInternalName($field_name);
      $destination_field_list = $destination->get($field_name);

      $origin_field_list = $origin->get($field_name);
      if ($this->checkPatchFieldAccess($destination_field_list, $origin_field_list)) {
        $destination->set($field_name, $origin_field_list->getValue());
      }
    }
    elseif ($origin instanceof ConfigEntityInterface && $destination instanceof ConfigEntityInterface) {
      // Second scenario: both are config entities.
      $destination->set($field_name, $origin->get($field_name));
    }
    else {
      throw new BadRequestHttpException('The serialized entity and the destination entity are of different types.');
    }
  }

  /**
   * Checks whether the given field should be PATCHed.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $original_field
   *   The original (stored) value for the field.
   * @param \Drupal\Core\Field\FieldItemListInterface $received_field
   *   The received value for the field.
   *
   * @return bool
   *   Whether the field should be PATCHed or not.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user sending the request is not allowed to update the
   *   field. Only thrown when the user could not abuse this information to
   *   determine the stored value.
   *
   * @internal
   *
   * @see \Drupal\rest\Plugin\rest\resource\EntityResource::checkPatchFieldAccess()
   */
  protected function checkPatchFieldAccess(FieldItemListInterface $original_field, FieldItemListInterface $received_field) {
    // If the user is allowed to edit the field, it is always safe to set the
    // received value. We may be setting an unchanged value, but that is ok.
    $field_edit_access = $original_field->access('edit', NULL, TRUE);
    if ($field_edit_access->isAllowed()) {
      return TRUE;
    }

    // The user might not have access to edit the field, but still needs to
    // submit the current field value as part of the PATCH request. For
    // example, the entity keys required by denormalizers. Therefore, if the
    // received value equals the stored value, return FALSE without throwing an
    // exception. But only for fields that the user has access to view, because
    // the user has no legitimate way of knowing the current value of fields
    // that they are not allowed to view, and we must not make the presence or
    // absence of a 403 response a way to find that out.
    if ($original_field->access('view') && $original_field->equals($received_field)) {
      return FALSE;
    }

    // It's helpful and safe to let the user know when they are not allowed to
    // update a field.
    $field_name = $received_field->getName();
    throw new EntityAccessDeniedHttpException($original_field->getEntity(), $field_edit_access, '/data/attributes/' . $field_name, sprintf('The current user is not allowed to PATCH the selected field (%s).', $field_name));
  }

  /**
   * Build a collection of the entities to respond with and access objects.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage to load the entities from.
   * @param int[] $ids
   *   Array of entity IDs.
   *
   * @return array
   *   An array keyed by entity ID containing the keys:
   *     - entity: the loaded entity or an access exception.
   *     - access: the access object.
   */
  protected function loadEntitiesWithAccess(EntityStorageInterface $storage, array $ids) {
    $output = [];
    foreach ($storage->loadMultiple($ids) as $entity) {
      $output[$entity->id()] = static::getEntityAndAccess($entity);
    }
    return $output;
  }

  /**
   * Get the object to normalize and the access based on the provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to test access for.
   *
   * @return array
   *   An array containing the keys:
   *     - entity: the loaded entity or an access exception.
   *     - access: the access object.
   *
   * @throws \Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
   *   Thrown when the current user is not allowed to GET the selected resource.
   */
  public static function getEntityAndAccess(EntityInterface $entity) {
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');
    $entity = $entity_repository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    $access = $entity->access('view', NULL, TRUE);
    // Accumulate the cacheability metadata for the access.
    $output = [
      'access' => $access,
      'entity' => $entity,
    ];
    if (!$access->isAllowed()) {
      $label_access = $entity->access('view label', NULL, TRUE);
      $output['access'] = $label_access->addCacheableDependency($output['access']);
      if ($label_access->isAllowed()) {
        $output['entity'] = new LabelOnlyEntity($entity);
      }
      else {
        // Pass an exception to the list of things to normalize.
        $output['entity'] = new EntityAccessDeniedHttpException($entity, $access, '/data', 'The current user is not allowed to GET the selected resource.');
      }
    }

    return $output;
  }

  /**
   * Checks if the given entity exists.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to test existence.
   *
   * @return bool
   *   Whether the entity already has been created.
   */
  protected function entityExists(EntityInterface $entity) {
    $entity_storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    return !empty($entity_storage->loadByProperties([
      'uuid' => $entity->uuid(),
    ]));
  }

}
