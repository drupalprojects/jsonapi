<?php


namespace Drupal\jsonapi\Configuration;

/**
 * @internal
 */
interface ResourceConfigInterface {

  /**
   * Gets the entity type id.
   *
   * @return string
   *   The entity type id.
   */
  public function getEntityTypeId();

  /**
   * Gets the type name.
   *
   * @return string
   *   The type name.
   */
  public function getTypeName();

  /**
   * Gets the path.
   *
   * @return string
   *   The path.
   */
  public function getPath();

  /**
   * Gets the bundle ID.
   *
   * @return string
   *   The bundleId.
   */
  public function getBundleId();

  /**
   * Gets the deserialization target class.
   *
   * @return string
   *   The deserialization target class.
   */
  public function getDeserializationTargetClass();

}
