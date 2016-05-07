<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValueInterface.
 */
namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class ContentEntityNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface ContentEntityNormalizerValueInterface extends ValueExtractorInterface {

  /**
   * Gets the values.
   *
   * @return mixed
   *   The values.
   */
  public function getValues();

}
