<?php


namespace Drupal\jsonapi\Normalizer;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class ContentEntityNormalizerInterface.
 *
 * @package Drupal\jsonapi\Normalizer
 */
interface ContentEntityNormalizerInterface {

  /**
   * Builds the normalizer value.
   *
   * @param \Drupal\core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $format
   *   The format to normalize.
   * @param array $context
   *   The context.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValueInterface
   *   The normalized value.
   */
  public function buildNormalizerValue(EntityInterface $entity, $format = NULL, array $context = array());

}
