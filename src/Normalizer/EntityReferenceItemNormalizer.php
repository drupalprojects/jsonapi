<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\EntityReferenceItemNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\hal\Normalizer\EntityReferenceItemNormalizer as HalEntityReferenceItemNormalizer;

/**
 * Converts the Drupal entity reference item object to HAL array structure.
 */
class EntityReferenceItemNormalizer extends HalEntityReferenceItemNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = array()) {
    /* @var $field_item \Drupal\Core\Field\FieldItemInterface */
    $target_entity = $field_item->get('entity')->getValue();

    // If this is not a content entity, let the parent implementation handle it,
    // only content entities are supported as embedded resources.
    if (!($target_entity instanceof FieldableEntityInterface)) {
      return parent::normalize($field_item, $format, $context);
    }
    return [
      'type' => $target_entity->getEntityTypeId(),
      'id' => $target_entity->id(),
    ];
  }

}
