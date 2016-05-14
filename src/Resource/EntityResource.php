<?php

namespace Drupal\jsonapi\Resource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\RequestCacheabilityDependency;
use Drupal\rest\ResourceResponse;
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
   * Instantiates a EntityResource object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceConfigInterface $resource_config
   *   The configuration for the resource.
   */
  public function __construct(ResourceConfigInterface $resource_config) {
    $this->resourceConfig = $resource_config;
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

    return $response;
  }
}
