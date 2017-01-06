<?php


namespace Drupal\jsonapi\Configuration;

/**
 * @internal
 */
interface ResourceConfigInterface {

  /**
   * Gets the entity type ID.
   *
   * @return string
   *   The entity type ID.
   *
   * @see \Drupal\Core\Entity\EntityInterface::getEntityTypeId
   */
  public function getEntityTypeId();

  /**
   * Gets the bundle.
   *
   * @return string
   *   The bundle. Is the same as the entity type ID if the entity type does not
   *   make use of different bundles.
   *
   * @see \Drupal\Core\Entity\EntityInterface::bundle
   */
  public function getBundle();

  /**
   * Gets the type name.
   *
   * @return string
   *   The type name.
   */
  public function getTypeName();

  /**
   * Gets the deserialization target class.
   *
   * @return string
   *   The deserialization target class.
   */
  public function getDeserializationTargetClass();

}
