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
class ConfigEntityNormalizer extends EntityNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ConfigEntityInterface::class;

  /**
   * {@inheritdoc}
   */
  protected function getFields($entity, $bundle) {
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    return $entity->toArray();
  }

  /**
   * {@inheritdoc}
   */
  protected function serializeField($field, $context, $format) {
    $output = $this->serializer->normalize($field, $format, $context);
    if (is_array($output)) {
      $output = new Value\FieldNormalizerValue(
        [new Value\FieldItemNormalizerValue($output)],
        1
      );
      $output->setPropertyType('attributes');
      return $output;
    }
    $field instanceof Relationship ?
      $output->setPropertyType('relationships') :
      $output->setPropertyType('attributes');
    return $output;
  }

}
