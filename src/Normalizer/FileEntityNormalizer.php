<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\hal\Normalizer\FileEntityNormalizer as HalFileEntityNormalizer;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class FileEntityNormalizer extends HalFileEntityNormalizer {

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = array('api_json');

}
