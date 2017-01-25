<?php

namespace Drupal\jsonapi\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\jsonapi\JsonApiSpec;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates custom (implementation-specific) query parameter names.
 *
 * @see http://jsonapi.org/format/#query-parameters
 *
 * @internal
 */
class CustomParameterNamesAccessCheck implements AccessInterface {

  /**
   * Validates the JSONAPI parameter names.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(Request $request) {
    $json_api_params = $request->attributes->get('_json_api_params', []);
    if (!$this->validate($json_api_params)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

  /**
   * Validates the JSON API query parameters.
   *
   * @see http://jsonapi.org/format/#document-member-names-reserved-characters
   *
   * @param string[] $json_api_params
   *   The JSONAPI parameters.
   *
   * @return bool
   */
  protected function validate(array $json_api_params) {
    foreach (array_keys($json_api_params) as $name) {
      // Ignore reserved (official) query parameters.
      if (in_array($name, JsonApiSpec::getReservedQueryParameters())) {
        continue;
      }

      if (!JsonApiSpec::isValidCustomQueryParameter($name)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
