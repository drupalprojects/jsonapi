<?php

namespace Drupal\jsonapi;

use Drupal\Core\Entity\EntityInterface;

/**
 * Value object decorating an Entity object; only its label is to be normalized.
 *
 * @internal
 */
class LabelOnlyEntity {

  /**
   * Constructs a LabelOnlyEntity value object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to only normalize its label.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Gets the decorated entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The label for which to only normalize its label.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Determines the entity type's (internal) label field name.
   */
  public function getLabelFieldName() {
    $label_field_name = $this->entity->getEntityType()->getKey('label');
    // @todo Remove this work-around after https://www.drupal.org/project/drupal/issues/2450793 lands.
    if ($this->entity->getEntityTypeId() === 'user') {
      $label_field_name = 'name';
    }
    return $label_field_name;
  }

}
