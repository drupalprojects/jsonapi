<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class EntityReferenceListNormalizerValue.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
class EntityReferenceNormalizerValue extends FieldNormalizerValue implements EntityReferenceNormalizerValueInterface {

  /**
   * Instantiate a EntityReferenceNormalizerValue object.
   *
   * @param EntityReferenceItemNormalizerValue[] $values
   *   The normalized result.
   */
  public function __construct(array $values, $cardinality) {
    array_walk($values, function ($field_item_value) {
      if (!$field_item_value instanceof EntityReferenceItemNormalizerValueInterface) {
        throw new \RuntimeException(sprintf('Unexpected normalizer item value for this %s.', get_called_class()));
      }
    });
    parent::__construct($values, $cardinality);
  }

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
