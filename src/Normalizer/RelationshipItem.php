<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;

/**
 * @internal
 */
class RelationshipItem {

  /**
   * The target key name.
   *
   * @param string
   */
  protected $targetKey = 'target_id';

  /**
   * The target entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface
   */
  protected $targetEntity;

  /**
   * The target resource config.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceConfigInterface
   */
  protected $targetResourceConfig;

  /**
   * The parent relationship.
   *
   * @var \Drupal\jsonapi\Normalizer\Relationship
   */
  protected $parent;

  /**
   * Relationship item constructor.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager.
   * @param \Drupal\Core\Entity\EntityInterface $target_entity
   *   The entity this relationship points to.
   * @param \Drupal\jsonapi\Normalizer\Relationship
   *   The parent of this item.
   * @param string $target_key
   *   The key name of the target relationship.
   */
  public function __construct(ResourceManagerInterface $resource_manager, EntityInterface $target_entity, Relationship $parent, $target_key = 'target_id') {
    $this->targetResourceConfig = $resource_manager->get(
      $target_entity->getEntityTypeId(),
      $target_entity->bundle()
    );
    $this->targetKey = $target_key;
    $this->targetEntity = $target_entity;
    $this->parent = $parent;
  }

  /**
   * Gets the target entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getTargetEntity() {
    return $this->targetEntity;
  }

  /**
   * Gets the targetResourceConfig.
   *
   * @return mixed
   */
  public function getTargetResourceConfig() {
    return $this->targetResourceConfig;
  }

  /**
   * Gets the relationship value.
   *
   * Defaults to the entity ID.
   *
   * @return string
   */
  public function getValue() {
    return [$this->targetKey => $this->getTargetEntity()->uuid()];
  }

  /**
   * Gets the relationship object that contains this relationship item.
   *
   * @return \Drupal\jsonapi\Normalizer\Relationship
   */
  public function getParent() {
    return $this->parent;
  }

}
