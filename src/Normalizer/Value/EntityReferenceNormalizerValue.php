<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class EntityReferenceListNormalizerValue.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
class EntityReferenceNormalizerValue extends FieldNormalizerValue implements EntityReferenceNormalizerValueInterface {

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    if (!$value = parent::rasterizeValue()) {
      // According to the JSON API specs empty relationships are either NULL or
      // an empty array.
      $value = $this->cardinality == 1 ? NULL : [];
    }
    return [
      'data' => $value,
    ];
  }

}
