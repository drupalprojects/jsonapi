<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class DocumentRootNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface DocumentRootNormalizerValueInterface extends ValueExtractorInterface {

  /**
   * Get the rasterized value.
   *
   * @return mixed
   *   The value.
   */
  public function rasterizeValue();

}
