<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Class FieldNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface FieldNormalizerValueInterface extends ValueExtractorInterface, RefinableCacheableDependencyInterface {

  /**
   * Gets the includes
   *
   * @return mixed
   *   The includes.
   */
  public function getIncludes();

  /**
   * Gets the propertyType.
   *
   * @return mixed
   *   The propertyType.
   */
  public function getPropertyType();

  /**
   * Sets the propertyType.
   *
   * @param mixed $property_type
   *   The propertyType to set.
   */
  public function setPropertyType($property_type);

}
