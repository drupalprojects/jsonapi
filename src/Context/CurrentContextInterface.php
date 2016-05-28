<?php

namespace Drupal\jsonapi\Context;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface CurrentContextInterface.
 *
 * An interface for accessing contextual information for the current request.
 *
 * @package \Drupal\jsonapi\Context
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
   * Returns the current route match.
   *
   * @return \Symfony\Component\Routing\Route
   *   The currently matched route.
   */
  public function getCurrentRoute();

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
   * Configures the current context from a request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function fromRequest(Request $request);

  /**
   * Configures the current context from a request stack.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function fromRequestStack(RequestStack $request_stack);

}
