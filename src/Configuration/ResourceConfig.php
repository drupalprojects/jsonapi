<?php

namespace Drupal\jsonapi\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Value object containing all metadata for a JSON API resource type.
 *
 * Used to generate routes (collection, individual, et cetera), generate
 * relationship links, and so on.
 *
 * @internal
 */
class ResourceConfig implements ResourceConfigInterface {

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The bundle ID.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The type name.
   *
   * @var string
   */
  protected $typeName;

  /**
   * The class to which a payload converts to.
   *
   * @var string
   */
  protected $deserializationTargetClass;

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName() {
    return $this->typeName;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeserializationTargetClass() {
    return $this->deserializationTargetClass;
  }

  /**
   * Instantiates a ResourceConfig object.
   *
   * @param string $entity_type_id
   *   An entity type ID.
   * @param string $bundle
   *   A bundle.
   * @param string $deserialization_target_class
   *   The deserialization target class.
   */
  public function __construct($entity_type_id, $bundle, $deserialization_target_class) {
    $this->entityTypeId = $entity_type_id;
    $this->bundle = $bundle;
    $this->deserializationTargetClass = $deserialization_target_class;

    $this->typeName = sprintf('%s--%s', $this->entityTypeId, $this->bundle);
  }

}
