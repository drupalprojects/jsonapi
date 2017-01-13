<?php

namespace Drupal\jsonapi;

/**
 * Defines a common interface for resource responses.
 *
 * @internal
 */
interface ResourceResponseInterface {

  /**
   * Returns response data that should be serialized.
   *
   * @return mixed
   *   Response data that should be serialized.
   */
  public function getResponseData();

}
