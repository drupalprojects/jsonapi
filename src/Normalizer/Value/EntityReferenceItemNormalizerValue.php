<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\Value\EntityReferenceItemNormalizerValue.
 */
namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class FieldItemNormalizerValue.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
class EntityReferenceItemNormalizerValue extends FieldItemNormalizerValue implements EntityReferenceItemNormalizerValueInterface {

  /**
   * Resource path.
   *
   * @param string
   */
  protected $resource;

  /**
   * Instantiates a EntityReferenceItemNormalizerValue object.
   *
   * @param array $values
   *   The values.
   * @param string $resource
   *   The resource path of the target entity.
   */
  public function __construct(array $values, $resource) {
    parent::__construct($values);
    $this->resource = $resource;
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    if (!$value = parent::rasterizeValue()) {
      return $value;
    }
    return [
      'type' => $this->resource,
      'id' => $value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setResource($resource) {
    $this->resource = $resource;
  }

}
