<?php

namespace Drupal\jsonapi\Configuration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class ResourceConfig.
 *
 * This object contains all the information needed to generate all the routes
 * associated with a JSON API type. In the future this is going to be
 * constructed (maybe?) from a configuration entity.
 *
 * @package Drupal\jsonapi\Configuration
 */
class ResourceConfig implements ResourceConfigInterface {

  /**
   * Holds the entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
  public function setEntityTypeId($entity_type_id) {
    $this->entityTypeId = $entity_type_id;
    $this->deserializationTargetClass = $this->entityTypeManager
      ->getDefinition($entity_type_id)
      ->getClass();
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
  public function setTypeName($type_name) {
    $this->typeName = $type_name;
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
  public function setPath($path) {
    $this->path = $path;
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
  public function setBundleId($bundle_id) {
    $this->bundleId = $bundle_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage() {
    return $this->entityTypeManager->getStorage($this->entityTypeId);
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

}
