<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class EntityReferenceFieldNormalizer.
 *
 * @package Drupal\jsonapi\Normalizer
 */
class EntityReferenceFieldNormalizer extends FieldNormalizer {

  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\EntityReferenceFieldItemListInterface';

  /**
   * Helper function to normalize field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   * @param string $format
   *   The format.
   * @param array $context
   *   The context array.
   *
   * @return array
   *   The array of normalized field items.
   */
  protected function normalizeFieldItems(FieldItemListInterface $field, $format, $context) {
    $normalizer_items = array();
    $includes = [];
    if (!$field->isEmpty()) {
      foreach ($field as $field_item) {
        $normalizer_items[] = $this->serializer->normalize($field_item, $format, $context);
      }
    }
    $cardinality = $field->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();
    return new Value\EntityReferenceNormalizerValue($normalizer_items, $cardinality);
  }


}
