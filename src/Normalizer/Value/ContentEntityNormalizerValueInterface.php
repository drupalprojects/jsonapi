<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Class ContentEntityNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface ContentEntityNormalizerValueInterface extends ValueExtractorInterface, RefinableCacheableDependencyInterface {

  /**
   * Gets the values.
   *
   * @return mixed
   *   The values.
   */
  public function getValues();

}
