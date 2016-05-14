<?php

namespace Drupal\jsonapi\Resource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\EntityCollection;
use Drupal\jsonapi\RequestCacheabilityDependency;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;


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
   * Instantiates a EntityResource object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceConfigInterface $resource_config
   *   The configuration for the resource.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ResourceConfigInterface $resource_config, EntityTypeManagerInterface $entity_type_manager) {
    $this->resourceConfig = $resource_config;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   *
   * @return ResourceResponse
   *   The response.
   */
  public function getIndividual(EntityInterface $entity) {
    $entity_access = $entity->access('view', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $response = new ResourceResponse($entity, 200);
    $this->addCacheabilityMetadata($response, $entity);
    return $response;
  }

  /**
   * Gets the collection of entities.
   *
   * @param Request $request
   *   The request object.
   *
   * @return ResourceResponse
   *   The response.
   */
  public function getCollection(Request $request) {
    // Instantiate the query for the filtering.
    $entity_type_id = $this->resourceConfig->getEntityTypeId();
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery();
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query->condition($entity_type->getKey('bundle'), $this->resourceConfig->getBundleId());
    // TODO: Add filtering support.
    $results = $query->execute();
    // TODO: Make this method testable by removing the "new".
    $entity_collection = new EntityCollection($storage->loadMultiple($results));
    $response = new ResourceResponse($entity_collection, 200);
    foreach ($entity_collection as $entity) {
      $this->addCacheabilityMetadata($response, $entity);
    }
    return $response;
  }

  /**
   * Adds cacheability metadata to an entity.
   *
   * @param ResourceResponse $response
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
}
