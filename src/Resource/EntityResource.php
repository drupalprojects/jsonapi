<?php

namespace Drupal\jsonapi\Resource;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\EntityCollection;
use Drupal\jsonapi\RequestCacheabilityDependency;
use Drupal\jsonapi\Query\QueryBuilderInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class EntityResource.
 *
 * @package Drupal\jsonapi\Resource
 */
class EntityResource implements EntityResourceInterface {

  /**
   * The resource config.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceConfigInterface
   */
  protected $resourceConfig;

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
   * The query builder service.
   *
   * @var \Drupal\jsonapi\Query\QueryBuilderInterface
   */
  protected $queryBuilder;

  /**
   * The current context service.
   *
   * @var \Drupal\jsonapi\Context\CurrentContextInterface
   */
  protected $currentContext;

  /**
   * Instantiates a EntityResource object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceConfigInterface $resource_config
   *   The configuration for the resource.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\Query\QueryBuilderInterface $query_builder
   *   The query builder.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity type field manager.
   * @param \Drupal\jsonapi\Context\CurrentContextInterface $current_context
   *   The current context.
   */
  public function __construct(ResourceConfigInterface $resource_config, EntityTypeManagerInterface $entity_type_manager, QueryBuilderInterface $query_builder, EntityFieldManagerInterface $field_manager, CurrentContextInterface $current_context) {
    $this->resourceConfig = $resource_config;
    $this->entityTypeManager = $entity_type_manager;
    $this->queryBuilder = $query_builder;
    $this->fieldManager = $field_manager;
    $this->currentContext = $current_context;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndividual(EntityInterface $entity) {
    $entity_access = $entity->access('view', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException('The current user is not allowed to GET the selected resource.');
    }
    $response = $this->buildWrappedResponse($entity);
    $this->addCacheabilityMetadata($response, $entity);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function createIndividual(EntityInterface $entity) {
    $entity_access = $entity->access('create', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException('The current user is not allowed to POST the selected resource.');
    }
    $entity->save();
    return $this->buildWrappedResponse($entity, 201);
  }

  /**
   * {@inheritdoc}
   */
  public function patchIndividual(EntityInterface $entity, EntityInterface $parsed_entity) {
    throw new \InvalidArgumentException('Operation not yet supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteIndividual(EntityInterface $entity) {
    $entity_access = $entity->access('delete', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException('The current user is not allowed to DELETE the selected resource.');
    }
    $entity->delete();
    return new ResourceResponse(NULL, 204);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollection(Request $request) {
    // Instantiate the query for the filtering.
    $entity_type_id = $this->resourceConfig->getEntityTypeId();

    // Set the current context from the request.
    $this->currentContext->fromRequest($request);

    $params = $request->attributes->get('_route_params');
    $query = $this->getCollectionQuery($entity_type_id, $params['_json_api_params']);

    // TODO: Need to wrap in a try/catch and respond with useful error.
    $results = $query->execute();

    // TODO: Make this method testable by removing the "new".
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_collection = new EntityCollection($storage->loadMultiple($results));
    return $this->respondWithCollection($entity_collection, $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getRelated(EntityInterface $entity, $related_field) {
    /* @var $field_list \Drupal\Core\Field\FieldItemListInterface */
    if (!($field_list = $entity->get($related_field)) || $field_list->getDataDefinition()->getType() != 'entity_reference') {
      throw new NotFoundHttpException(sprintf('The relationship %s is not present in this resource.', $related_field));
    }
    $data_definition = $field_list->getDataDefinition();
    if (!$is_multiple = $data_definition->getFieldStorageDefinition()->isMultiple()) {
      return $this->getIndividual($field_list->entity);
    }
    $entities = [];
    foreach ($field_list as $field_item) {
      /* @var \Drupal\Core\Entity\EntityInterface $entity_item */
      $entity_item = $field_item->entity;
      $entities[$entity_item->id()] = $entity_item;
    }
    $entity_collection = new EntityCollection($entities);
    $entity_type_id = $field_list->getSetting('target_type');
    return $this->respondWithCollection($entity_collection, $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationship(EntityInterface $entity, $related_field, $response_code = 200) {
    /* @var $field_list \Drupal\Core\Field\FieldItemListInterface */
    if (!($field_list = $entity->{$related_field}) || $field_list->getDataDefinition()->getType() != 'entity_reference') {
      throw new NotFoundHttpException(sprintf('The relationship %s is not present in this resource.', $related_field));
    }
    $response = $this->buildWrappedResponse($field_list, $response_code);
    $this->addCacheabilityMetadata($response, $entity);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function createRelationship(EntityInterface $entity, $related_field, $parsed_field_list) {
    if ($parsed_field_list instanceof Response) {
      // This usually means that there was an error, so there is no point on
      // processing further.
      return $parsed_field_list;
    }
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list */
    $entity_access = $entity->access('update', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException('The current user is not allowed to POST the selected resource.');
    }
    /* @var $field_list \Drupal\Core\Field\FieldItemListInterface */
    if (!($field_list = $entity->{$related_field}) || $field_list->getDataDefinition()->getType() != 'entity_reference') {
      throw new NotFoundHttpException(sprintf('The relationship %s is not present in this resource.', $related_field));
    }
    // According to the specification, you are only allowed to POST to a
    // relationship if it is a to-many relationship.
    $is_multiple = $field_list->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->isMultiple();
    if (!$is_multiple) {
      throw new ConflictHttpException(sprintf('You can only POST to to-many relationships. %s is a to-one relationship.', $related_field));
    }

    $field_access = $field_list->access('update', NULL, TRUE);
    if (!$field_access->isAllowed()) {
      throw new AccessDeniedHttpException(sprintf('The current user is not allowed to PATCH the selected field (%s).', $field_name));
    }
    // Time to save the relationship.
    foreach ($parsed_field_list as $field_item) {
      $field_list->appendItem($field_item->getValue());
    }
    $entity->save();
    return $this->getRelationship($entity, $related_field, 201);
  }

  /**
   * {@inheritdoc}
   */
  public function patchRelationship(EntityInterface $entity, $related_field, $parsed_field_list) {
    if ($parsed_field_list instanceof Response) {
      // This usually means that there was an error, so there is no point on
      // processing further.
      return $parsed_field_list;
    }
    /* @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list */
    $entity_access = $entity->access('update', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException('The current user is not allowed to POST the selected resource.');
    }
    /* @var $field_list \Drupal\Core\Field\FieldItemListInterface */
    if (!($field_list = $entity->{$related_field}) || $field_list->getDataDefinition()->getType() != 'entity_reference') {
      throw new NotFoundHttpException(sprintf('The relationship %s is not present in this resource.', $related_field));
    }
    // According to the specification, PATCH works a little bit different if the
    // relationship is to-one or to-many.
    $is_multiple = $field_list->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->isMultiple();
    $method = $is_multiple ? 'doPatchMultipleRelationship' : 'doPatchIndividualRelationship';
    $this->{$method}($entity, $parsed_field_list);
    $entity->save();
    return $this->getRelationship($entity, $related_field, 201);
  }

  /**
   * Update a to-one relationship.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $parsed_field_list
   *   The entity reference field list of items to add, or a response object in
   *   case of error.
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
   */
  protected function doPatchMultipleRelationship(EntityInterface $entity, EntityReferenceFieldItemListInterface $parsed_field_list) {
    $field_name = $parsed_field_list->getName();
    $field_access = $parsed_field_list->access('update', NULL, TRUE);
    if (!$field_access->isAllowed()) {
      throw new AccessDeniedHttpException(sprintf('The current user is not allowed to PATCH the selected field (%s).', $field_name));
    }
    $entity->{$field_name} = $parsed_field_list;
  }

  /**
   * Gets a basic query for a collection.
   *
   * @param string $entity_type_id
   *   The entity type for the entity query.
   * @param \Drupal\jsonapi\Routing\Param\JsonApiParamInterface[] $params
   *   The parameters for the query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   A new query.
   */
  protected function getCollectionQuery($entity_type_id, $params) {
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    $query = $this->queryBuilder->newQuery($entity_type);

    // Limit this query to the bundle type for this resource.
    if ($bundle_key = $entity_type->getKey('bundle')) {
      $query->condition(
        $bundle_key, $this->resourceConfig->getBundleId()
      );
    }

    return $query;
  }

  /**
   * Adds cacheability metadata to an entity.
   *
   * @param \Drupal\rest\ResourceResponse $response
   *   The REST response.
   * @param EntityInterface $entity
   *   The entity.
   */
  protected function addCacheabilityMetadata(ResourceResponse $response, EntityInterface $entity) {
    $entity_access = $entity->access('view', NULL, TRUE);
    $response->addCacheableDependency($entity);
    $response->addCacheableDependency($entity_access);
    // Make sure that different sparse fieldsets are cached differently.
    $response->addCacheableDependency(new RequestCacheabilityDependency());
    foreach ($entity as $field_name => $field) {
      /* @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_access = $field->access('view', NULL, TRUE);
      $response->addCacheableDependency($field_access);

      if (!$field_access->isAllowed()) {
        $entity->set($field_name, NULL);
      }
    }
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
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse($data, $response_code = 200, array $headers = array()) {
    return new ResourceResponse(new DocumentWrapper($data), $response_code, $headers);
  }

  /**
   * Respond with an entity collection.
   *
   * @param \Drupal\jsonapi\EntityCollection $entity_collection
   *   The collection of entites.
   * @param string $entity_type_id
   *   The entity type.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  protected function respondWithCollection(EntityCollection $entity_collection, $entity_type_id) {
    $response = $this->buildWrappedResponse($entity_collection);

    // When a new change to any entity in the resource happens, we cannot ensure
    // the validity of this cached list. Add the list tag to deal with that.
    $list_tag = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getListCacheTags();
    $response->getCacheableMetadata()->setCacheTags($list_tag);
    // Add a cache tag for every entity in the list.
    foreach ($entity_collection as $entity) {
      $this->addCacheabilityMetadata($response, $entity);
    }
    return $response;
  }

}
