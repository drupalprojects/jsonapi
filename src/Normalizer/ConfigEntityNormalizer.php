<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;

/**
 * Converts the Drupal config entity object to a JSON API array structure.
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
  protected function serializeField($field, array $context, $format) {
    $output = $this->serializer->normalize($field, $format, $context);
    if (is_array($output)) {
      $output = new FieldNormalizerValue(
        [new FieldItemNormalizerValue($output)],
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
