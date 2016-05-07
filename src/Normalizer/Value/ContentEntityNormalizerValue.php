<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValue.
 */
namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class ContentEntityNormalizerValue.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
class ContentEntityNormalizerValue implements ContentEntityNormalizerValueInterface {

  /**
   * The values.
   *
   * @param array
   */
  protected $values;

  /**
   * The includes.
   *
   * @param array
   */
  protected $includes;

  /**
   * Instantiate a ContentEntityNormalizerValue object.
   *
   * @param FieldNormalizerValueInterface[] $values
   *   The normalized result.
   */
  public function __construct(array $values) {
    $this->values = $values;
    // Get an array of arrays of includes.
    $this->includes = array_map(function ($value) {
      return $value->getIncludes();
    }, $values);
    // Flatten the includes.
    $this->includes = array_reduce($this->includes, function ($carry, $includes) {
      return array_merge($carry, $includes);
    }, []);
    // Filter the empty values.
    $this->includes = array_filter($this->includes);
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    return array_map(function ($value) {
      return $value->rasterizeValue();
    }, $this->values);

  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeIncludes() {
    return array_map(function ($include) {
      $include->rasterizeValue();
    }, $this->includes);
  }
  
  /**
   * Gets the values.
   *
   * @return mixed
   *   The values.
   */
  public function getValues() {
    return $this->values;
  }

}
