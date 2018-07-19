<?php

namespace Drupal\jsonapi\ResourceType;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Entity\ContentEntityNullStorage;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/**
 * Provides a repository of all JSON API resource types.
 *
 * Contains the complete set of ResourceType value objects, which are auto-
 * generated based on the Entity Type Manager and Entity Type Bundle Info: one
 * JSON API resource type per entity type bundle. So, for example:
 * - node--article
 * - node--page
 * - node--…
 * - user--user
 * - …
 *
 * @see \Drupal\jsonapi\ResourceType\ResourceType
 *
 * @internal
 */
class ResourceTypeRepository implements ResourceTypeRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * All JSON API resource types.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType[]
   */
  protected $all = [];

  /**
   * Class to instantiate for resource type objects.
   *
   * @var string
   */
  const RESOURCE_TYPE_CLASS = ResourceType::class;

  /**
   * Instantiates a ResourceTypeRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  // @codingStandardsIgnoreStart
  // @todo implement \Drupal\Core\Plugin\CachedDiscoveryClearerInterface?
  // @todo implement \Drupal\Component\Plugin\Discovery\DiscoveryInterface?
  public function clearCachedDefinitions() {
    $this->all = [];
  }
  // @codingStandardsIgnoreEnd

  /**
   * {@inheritdoc}
   */
  public function all() {
    if (!$this->all) {
      $entity_type_ids = array_keys($this->entityTypeManager->getDefinitions());
      foreach ($entity_type_ids as $entity_type_id) {
        $resource_type_class = static::RESOURCE_TYPE_CLASS;
        $this->all = array_merge($this->all, array_map(function ($bundle) use ($entity_type_id, $resource_type_class) {
          $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
          $raw_fields = $this->getAllFieldNames($entity_type, $bundle);
          return new $resource_type_class(
            $entity_type_id,
            $bundle,
            $entity_type->getClass(),
            $entity_type->isInternal(),
            static::isLocatableResourceType($entity_type),
            static::isMutableResourceType($entity_type),
            static::getFieldMapping($raw_fields, $entity_type)
          );
        }, array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id))));
      }
      foreach ($this->all as $resource_type) {
        $relatable_resource_types = $this->calculateRelatableResourceTypes($resource_type);
        $resource_type->setRelatableResourceTypes($relatable_resource_types);
      }
    }
    return $this->all;
  }

  /**
   * {@inheritdoc}
   */
  public function get($entity_type_id, $bundle) {
    assert(is_string($bundle) && !empty($bundle), 'A bundle ID is required. Bundleless entity types should pass the entity type ID again.');
    if (empty($entity_type_id)) {
      throw new PreconditionFailedHttpException('Server error. The current route is malformed.');
    }
    foreach ($this->all() as $resource) {
      if ($resource->getEntityTypeId() == $entity_type_id && $resource->getBundle() == $bundle) {
        return $resource;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getByTypeName($type_name) {
    foreach ($this->all() as $resource) {
      if ($resource->getTypeName() == $type_name) {
        return $resource;
      }
    }
    return NULL;
  }

  /**
   * Gets the field mapping for the given field names and entity type.
   *
   * @param string[] $field_names
   *   All field names on a bundle of the given entity type.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type for which to get the field mapping.
   *
   * @return array
   *   An array with:
   *   - keys are (real/internal) field names
   *   - values are either FALSE (indicating the field is not exposed despite
   *     not being internal), TRUE (indicating the field should be exposed under
   *     its internal name) or a string (indicating the field should not be
   *     exposed using its internal name, but the name specified in the string)
   */
  protected static function getFieldMapping(array $field_names, EntityTypeInterface $entity_type) {
    assert(Inspector::assertAllStrings($field_names));
    assert($entity_type instanceof ContentEntityTypeInterface || $entity_type instanceof ConfigEntityTypeInterface);

    $mapping = [];

    // JSON API resource identifier objects are sufficient to identify
    // entities. By exposing all fields as attributes, we expose unwanted,
    // confusing or duplicate information:
    // - exposing an entity's ID (which is not a UUID) is bad, but it's
    //   necessary for certain Drupal-coupled clients, so we alias it.
    // - exposing its UUID as an attribute is useless (it's already part of
    //   the mandatory "id" attribute in JSON API)
    // @see http://jsonapi.org/format/#document-resource-identifier-objects
    $mapping[$entity_type->getKey('uuid')] = FALSE;
    $id_field_name = $entity_type->getKey('id');
    $mapping[$id_field_name] = "drupal_internal__$id_field_name";
    if ($entity_type instanceof ConfigEntityTypeInterface) {
      // The '_core' key is reserved by Drupal core to handle complex edge cases
      // correctly. Data in the '_core' key is irrelevant to clients reading
      // configuration, and is not allowed to be set by clients writing
      // configuration: it is for Drupal core only, and managed by Drupal core.
      // @see https://www.drupal.org/node/2653358
      $mapping['_core'] = FALSE;
    }

    // For all other fields,  use their internal field name also as their public
    // field name.  Unless they're called "id" or "type": those names are
    // reserved by the JSON API spec.
    // @see http://jsonapi.org/format/#document-resource-object-fields
    foreach (array_diff($field_names, array_keys($mapping)) as $field_name) {
      if ($field_name === 'id' || $field_name === 'type') {
        $alias = $entity_type->id() . '_' . $field_name;
        if (isset($field_name[$alias])) {
          throw new \LogicException('The generated alias conflicts with an existing field. Please report this in the JSON API issue queue!');
        }
        $mapping[$field_name] = $alias;
        continue;
      }

      // The default, which applies to most fields: expose as-is.
      $mapping[$field_name] = TRUE;
    }

    return $mapping;
  }

  /**
   * Gets all field names for a given entity type and bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type for which to get all field names.
   * @param string $bundle
   *   The bundle for which to get all field names.
   *
   * @return string[]
   *   All field names.
   */
  protected function getAllFieldNames(EntityTypeInterface $entity_type, $bundle) {
    if ($entity_type instanceof ContentEntityTypeInterface) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions(
        $entity_type->id(),
        $bundle
      );
      return array_keys($field_definitions);
    }
    elseif ($entity_type instanceof ConfigEntityTypeInterface) {
      $base_properties = ['id', 'type', 'uuid', '_core'];
      try {
        // @todo Remove this conditional expression when Drupal core supported version is >= 8.6.
        // @see https://www.drupal.org/project/drupal/issues/2986901.
        $export_properties = $entity_type->getPropertiesToExport() ?: $base_properties;
      }
      catch (SchemaIncompleteException $e) {
        $export_properties = $base_properties;
      }
      finally {
        return $export_properties;
      }
    }
    else {
      throw new \LogicException("Only content and config entity types are supported.");
    }
  }

  /**
   * Whether an entity type is a mutable resource type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to assess.
   *
   * @return bool
   *   TRUE if the entity type is mutable, FALSE otherwise.
   */
  protected static function isMutableResourceType(EntityTypeInterface $entity_type) {
    return !$entity_type instanceof ConfigEntityTypeInterface;
  }

  /**
   * Whether an entity type is a locatable resource type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to assess.
   *
   * @return bool
   *   TRUE if the entity type is locatable, FALSE otherwise.
   */
  protected static function isLocatableResourceType(EntityTypeInterface $entity_type) {
    return $entity_type->getStorageClass() !== ContentEntityNullStorage::class;
  }

  /**
   * Calculates relatable JSON API resource types for a given resource type.
   *
   * This method has no affect after being called once.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type repository.
   *
   * @return array
   *   The relatable JSON API resource types, keyed by field name.
   */
  protected function calculateRelatableResourceTypes(ResourceType $resource_type) {
    // For now, only fieldable entity types may contain relationships.
    $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
    if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions(
        $resource_type->getEntityTypeId(),
        $resource_type->getBundle()
      );

      $relatable_internal = array_map(function ($field_definition) {
        return $this->getRelatableResourceTypesFromFieldDefinition($field_definition);
      }, array_filter($field_definitions, function ($field_definition) {
        return $this->isReferenceFieldDefinition($field_definition);
      }));

      $relatable_public = [];
      foreach ($relatable_internal as $internal_field_name => $value) {
        $relatable_public[$resource_type->getPublicName($internal_field_name)] = $value;
      }
      return $relatable_public;
    }
    return [];
  }

  /**
   * Get relatable resource types from a field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition from which to calculate relatable JSON API resource
   *   types.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType[]
   *   The JSON API resource types with which the given field may have a
   *   relationship.
   */
  protected function getRelatableResourceTypesFromFieldDefinition(FieldDefinitionInterface $field_definition) {
    $item_definition = $field_definition->getItemDefinition();

    $entity_type_id = $item_definition->getSetting('target_type');
    $handler_settings = $item_definition->getSetting('handler_settings');

    $has_target_bundles = isset($handler_settings['target_bundles']) && !empty($handler_settings['target_bundles']);
    $target_bundles = $has_target_bundles ?
      $handler_settings['target_bundles']
      : $this->getAllBundlesForEntityType($entity_type_id);

    return array_map(function ($target_bundle) use ($entity_type_id) {
      return $this->get($entity_type_id, $target_bundle);
    }, $target_bundles);
  }

  /**
   * Determines if a given field definition is a reference field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition to inspect.
   *
   * @return bool
   *   TRUE if the field definition is found to be a reference field. FALSE
   *   otherwise.
   */
  protected function isReferenceFieldDefinition(FieldDefinitionInterface $field_definition) {
    /* @var \Drupal\Core\Field\TypedData\FieldItemDataDefinition $item_definition */
    $item_definition = $field_definition->getItemDefinition();
    $main_property = $item_definition->getMainPropertyName();
    $property_definition = $item_definition->getPropertyDefinition($main_property);
    return $property_definition instanceof DataReferenceTargetDefinition;
  }

  /**
   * Gets all bundle IDs for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type for which to get bundles.
   *
   * @return string[]
   *   The bundle IDs.
   */
  protected function getAllBundlesForEntityType($entity_type_id) {
    return array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));
  }

}
