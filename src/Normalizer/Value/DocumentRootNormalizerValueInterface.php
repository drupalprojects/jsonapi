<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class DocumentRootNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface DocumentRootNormalizerValueInterface extends ValueExtractorInterface {

  /**
   * Gets a flattened list of includes in all the chain.
   *
   * @return ContentEntityNormalizerValueInterface[]
   *   The array of included relationships.
   */
  public function getIncludes();

}
