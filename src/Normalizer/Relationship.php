<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\Resource\EntityCollectionInterface;

/**
 * Use this class to create a relationship in your normalizer without having an
 * entity reference field: allows for "virtual" relationships that are not
 * backed by a stored entity reference.
 *
 * @internal
 */
class Relationship implements AccessibleInterface {

  /**
   * Cardinality.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The entity that holds the relationship.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $hostEntity;

  /**
   * The field name.
   *
   * @var string
   */
  protected $propertyName;

  /**
   * The resource manager.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * The relationship items.
   *
   * @var \Drupal\jsonapi\Normalizer\RelationshipItem[]
   */
  protected $items;

  /**
   * Relationship constructor.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager.
   * @param string $field_name
   *   The name of the relationship.
   * @param int $cardinality
   *   The relationship cardinality.
   * @param \Drupal\jsonapi\Resource\EntityCollectionInterface $entities
   *   A collection of entities.
   * @param \Drupal\Core\Entity\EntityInterface $host_entity
   *   The host entity.
   * @param string $target_key
   *   The property name of the relationship id.
   */
  public function __construct(ResourceManagerInterface $resource_manager, $field_name, $cardinality = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED, EntityCollectionInterface $entities, EntityInterface $host_entity, $target_key = 'target_id') {
    $this->resourceManager = $resource_manager;
    $this->propertyName = $field_name;
    $this->cardinality = $cardinality;
    $this->hostEntity = $host_entity;
    $this->items = [];
    foreach ($entities as $entity) {
      $this->items[] = new RelationshipItem(
        $resource_manager,
        $entity,
        $this,
        $target_key
      );
    }
  }

  /**
   * Gets the cardinality.
   *
   * @return mixed
   */
  public function getCardinality() {
    return $this->cardinality;
  }

  /**
   * Gets the host entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getHostEntity() {
    return $this->hostEntity;
  }

  /**
   * Sets the host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $hostEntity
   */
  public function setHostEntity(EntityInterface $hostEntity) {
    $this->hostEntity = $hostEntity;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Hard coded to TRUE. Revisit this if we need more control over this.
    return TRUE;
  }

  /**
   * Gets the field name.
   *
   * @return string
   */
  public function getPropertyName() {
    return $this->propertyName;
  }

  /**
   * Gets the items.
   *
   * @return \Drupal\jsonapi\Normalizer\RelationshipItem[]
   */
  public function getItems() {
    return $this->items;
  }

}
