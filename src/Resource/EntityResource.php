<?php

namespace Drupal\jsonapi\Resource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\EntityCollection;
use Drupal\jsonapi\RequestCacheabilityDependency;
use Drupal\jsonapi\Routing\Param\JsonApiParamInterface;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
   * {@inheritdoc}
   */
  public function getIndividual(EntityInterface $entity) {
    $entity_access = $entity->access('view', NULL, TRUE);
    if (!$entity_access->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $response = $this->buildWrappedResponse($entity);
    $this->addCacheabilityMetadata($response, $entity);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollection(Request $request) {
    // Instantiate the query for the filtering.
    $entity_type_id = $this->resourceConfig->getEntityTypeId();
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $query = $storage->getQuery();
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query->condition($entity_type->getKey('bundle'), $this->resourceConfig->getBundleId());
    $params = $request->attributes->get('_route_params');
    $params = $params['_json_api_params'];
    // Apply the filters.
    if (!empty($params['filter'])) {
      $this->applyFiltersForList($query, $params['filter']);
    }
    $results = $query->execute();
    // TODO: Make this method testable by removing the "new".
    $entity_collection = new EntityCollection($storage->loadMultiple($results));
    $response = $this->buildWrappedResponse($entity_collection);
    foreach ($entity_collection as $entity) {
      $this->addCacheabilityMetadata($response, $entity);
    }
    return $response;
  }

  /**
   * Applies the filters to the query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The entity query.
   * @param \Drupal\jsonapi\Routing\Param\JsonApiParamInterface $filter
   *   The filter parameter.
   */
  protected function applyFiltersForList(QueryInterface $query, JsonApiParamInterface $filter) {
    foreach ($filter->get() as $field_name => $filter_info) {
      // Deal with multivalue operators.
      if ($filter_info['multivalue']) {
        // Add a single condition using all the values and one operator.
        $query->condition($field_name, $filter_info['value'], $filter_info['operator'][0]);
      }
      else {
        // For every value in the filter, add a condition to the query.
        foreach ($filter_info['value'] as $index => $item) {
          $query->condition($field_name, $item, $filter_info['operator'][$index]);
        }
      }
    }
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

  /**
   * Builds a response with the appropriate wrapped document.
   *
   * @param mixed $data
   *   The data to wrap.
   *
   * @return ResourceResponse
   *   The response.
   */
  protected function buildWrappedResponse($data) {
    return new ResourceResponse(new DocumentWrapper($data), 200);
  }

}
