<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValueInterface.
 */
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
   * @param ContentEntityNormalizerValueInterface $include
   *   The included entity.
   */
  public function setInclude(ContentEntityNormalizerValueInterface $include);

  /**
   * Gets the include.
   *
   * @return ContentEntityNormalizerValueInterface
   *   The include.
   */
  public function getInclude();

}
