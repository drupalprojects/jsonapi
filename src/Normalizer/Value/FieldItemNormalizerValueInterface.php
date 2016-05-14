<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class FieldItemNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface FieldItemNormalizerValueInterface extends ValueExtractorInterface {

  /**
   * Add an include.
   *
   * @param DocumentRootNormalizerValueInterface $include
   *   The included entity.
   */
  public function setInclude(DocumentRootNormalizerValueInterface $include);

  /**
   * Gets the include.
   *
   * @return ContentEntityNormalizerValueInterface
   *   The include.
   */
  public function getInclude();

}
