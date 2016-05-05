<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\FieldNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Converts the Drupal field structure to HAL array structure.
 */
class FieldNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\FieldItemListInterface';

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = array('api_json');

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = array()) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field */
    return $this->normalizeFieldItems($field, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    throw new \Exception('Denormalization not implemented for JSON API');
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
    $normalized_items = array();
    if (!$field->isEmpty()) {
      foreach ($field as $field_item) {
        $normalized_items[] = $this
          ->serializer
          ->normalize($field_item, $format, $context);
      }
    }
    $cardinality = $field->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getCardinality();
    // If cardinality is 1 then return the item directly.
    return $cardinality == 1 ?
      reset($normalized_items) :
      $normalized_items;

  }

}
