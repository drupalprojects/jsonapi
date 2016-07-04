<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\jsonapi\RelationshipInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

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
  protected function getFields($entity, $bundle_id) {
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    return $entity->toArray();
  }

  /**
   * {@inheritdoc}
   */
  protected function serializeField($field, $context, $format) {
    $output = $this->serializer->normalize($field, $format, $context);
    $field instanceof RelationshipInterface ?
      $output->setPropertyType('relationships') :
      $output->setPropertyType('attributes');
    return $output;
  }

}
