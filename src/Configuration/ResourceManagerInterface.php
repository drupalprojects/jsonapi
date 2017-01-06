<?php


namespace Drupal\jsonapi\Configuration;

/**
 * Class ResourceManagerInterface.
 *
 * @package Drupal\jsonapi
 */
interface ResourceManagerInterface {

  /**
   * Get all the resource configuration objects.
   *
   * @return ResourceConfigInterface[]
   *   The list of resource configs representing JSON API types.
   */
  public function all();

  /**
   * Finds a resource config.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The id for the bundle to find.
   *
   * @return ResourceConfigInterface
   *   The resource config found. NULL if none was found.
   */
  public function get($entity_type_id, $bundle);

}
