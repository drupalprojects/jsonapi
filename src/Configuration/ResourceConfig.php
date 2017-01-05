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
  protected $bundleId;

  /**
   * The type name.
   *
   * @var string
   */
  protected $typeName;

  /**
   * The base resource path.
   *
   * @var string
   */
  protected $path;

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
  public function getPath() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleId() {
    return $this->bundleId;
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
   * @param string $bundle_id
   *   A bundle ID.
   * @param string $deserialization_target_class
   *   The deserialization target class.
   */
  public function __construct($entity_type_id, $bundle_id, $deserialization_target_class) {
    $this->entityTypeId = $entity_type_id;
    $this->bundleId = $bundle_id;
    $this->deserializationTargetClass = $deserialization_target_class;

    $this->typeName = sprintf('%s--%s', $this->entityTypeId, $this->bundleId);
    $this->path= sprintf('/%s/%s', $this->entityTypeId, $this->bundleId);
  }

}
