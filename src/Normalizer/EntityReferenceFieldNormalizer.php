<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class EntityReferenceFieldNormalizer.
 *
 * @package Drupal\jsonapi\Normalizer
 */
class EntityReferenceFieldNormalizer extends FieldNormalizer {

  protected $supportedInterfaceOrClass = EntityReferenceFieldItemListInterface::class;

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    if (!parent::supportsNormalization($data, $format)) {
      return FALSE;
    }
    $target_type = $data->getSetting('target_type');
    return !is_subclass_of(
      \Drupal::entityTypeManager()->getDefinition($target_type),
      'Drupal\Core\Config\Entity\ConfigEntityTypeInterface'
    );
  }

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
