<?php

namespace Drupal\jsonapi\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates custom parameter names.
 *
 * @internal
 */
class CustomParameterNames implements AccessInterface {

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
   * Validates the JSONAPI parameters.
   *
   * @see http://jsonapi.org/format/#document-member-names-reserved-characters
   *
   * @param string[] $json_api_params
   *   The JSONAPI parameters.
   *
   * @return bool
   */
  protected function validate(array $json_api_params) {
    $valid = TRUE;

    foreach (array_keys($json_api_params) as $name) {
      if (strpbrk($name, "+,.[]!”#$%&’()*/:;<=>?@\\^`{}~|\x0\x1\x2\x3\x4\x5\x6\x7\x8\x9\xA\xB\xC\xD\xE\xF\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")) {
        $valid = FALSE;
        break;
      }

      if (strpbrk($name[0], '-_ ') || strpbrk($name[strlen($name) - 1], '-_ ')) {
        $valid = FALSE;
        break;
      }
    }

    return $valid;
  }

}
