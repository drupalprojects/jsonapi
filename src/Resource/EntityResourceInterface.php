<?php


namespace Drupal\jsonapi\Resource;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class EntityResourceInterface.
 *
 * @package Drupal\jsonapi\Resource
 */
interface EntityResourceInterface {

  /**
   * Gets the individual entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function getIndividual(EntityInterface $entity);

  /**
   * Gets the collection of entities.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function getCollection(Request $request);

  /**
   * Gets the related resource.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function getRelated(EntityInterface $entity, $related_field);

  /**
   * Gets the relationship of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The requested entity.
   * @param string $related_field
   *   The related field name.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function getRelationship(EntityInterface $entity, $related_field);

}
