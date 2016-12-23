<?php

namespace Drupal\jsonapi\Context;

/**
 * Interface CurrentContextInterface.
 *
 * An interface for accessing contextual information for the current request.
 *
 * @package \Drupal\jsonapi\Context
 *
 * @internal
 */
interface CurrentContextInterface {

  /**
   * Returns a ResouceConfig for the current request.
   *
   * @return \Drupal\jsonapi\Configuration\ResourceConfigInterface
   *   The ResourceConfig object corresponding to the current request.
   */
  public function getResourceConfig();

  /**
   * Checks if the request is on a relationship.
   *
   * @return bool
   *   TRUE if the request is on a relationship. FALSE otherwise.
   */
  public function isOnRelationship();

  /**
   * Returns the resource manager.
   *
   * @return \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  public function getResourceManager();

  /**
   * Get a value by key from the _json_api_params route parameter.
   *
   * @param string $parameter_key
   *   The key by which to retrieve a route parameter.
   *
   * @return mixed
   *   The JSON API provided parameter.
   */
  public function getJsonApiParameter($parameter_key);

  /**
   * Determines, whether the JSONAPI extension was requested.
   *
   * @todo Find a better place for such a JSONAPI derived information.
   *
   * @param string $extension_name
   *   The extension name.
   *
   * @return bool
   *   Returns TRUE, if the extension has been found.
   */
  public function hasExtension($extension_name);

  /**
   * Returns a list of requested extensions.
   *
   * @return string[]
   *   The extension names.
   */
  public function getExtensions();

}
