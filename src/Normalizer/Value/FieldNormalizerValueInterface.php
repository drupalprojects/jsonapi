<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface.
 */
namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class FieldNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface FieldNormalizerValueInterface extends ValueExtractorInterface {

  /**
   * Gets the includes
   *
   * @return mixed
   *   The includes.
   */
  public function getIncludes();
  
  /**
   * Gets the propertyType
   *
   * @return mixed
   *   The propertyType.
   */
  public function getPropertyType();
  
  /**
   * Sets the propertyType
   *
   * @param mixed $propertyType
   *   The propertyType to set.
   */
  public function setPropertyType($propertyType);
  
}
