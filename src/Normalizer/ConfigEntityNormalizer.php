<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Class ConfigEntityNormalizer.
 *
 * Converts a configuration entity into the JSON API value rasterizable object.
 *
 * @package Drupal\jsonapi\Normalizer
 */
class ConfigEntityNormalizer extends ContentEntityNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ConfigEntityInterface::class;

  /**
   * Gets the field names for the given entity.
   *
   * @param mixed $entity
   *   The entity.
   *
   * @return array
   *   The fields.
   */
  protected function getFields($entity) {
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    return $entity->toArray();
  }

  /**
   * Serializes a given field.
   *
   * @param mixed $field
   *   The field to serialize.
   * @param array $context
   *   The normalization context.
   * @param string $format
   *   The serialization format.
   *
   * @return Value\FieldNormalizerValueInterface
   *   The normalized value.
   */
  protected function serializeField($field, $context, $format) {
    $output = $this->serializer->normalize($field, $format, $context);
    $output->setPropertyType('attributes');
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    throw new \Exception('Denormalization not implemented for JSON API');
  }

}
