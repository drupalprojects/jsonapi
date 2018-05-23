<?php

namespace Drupal\jsonapi\Context;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinition;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Service which resolves public field names to and from Drupal field names.
 *
 * @internal
 */
class FieldResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The entity type bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The JSON API resource type repository service.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Creates a FieldResolver instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The bundle info service.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * Resolves external field expressions into internal field expressions.
   *
   * It is often required to reference data which may exist across a
   * relationship. For example, you may want to sort a list of articles by
   * a field on the article author's representative entity. Or you may wish
   * to filter a list of content by the name of referenced taxonomy terms.
   *
   * In an effort to simplify the referenced paths and align them with the
   * structure of JSON API responses and the structure of the hypothetical
   * "reference document" (see link), it is possible to alias field names and
   * elide the "entity" keyword from them (this word is used by the entity query
   * system to traverse entity references).
   *
   * This method takes this external field expression and and attempts to
   * resolve any aliases and/or abbreviations into a field expression that will
   * be compatible with the entity query system.
   *
   * @link http://jsonapi.org/recommendations/#urls-reference-document
   *
   * Example:
   *   'uid.field_first_name' -> 'uid.entity.field_first_name'.
   *   'author.firstName' -> 'field_author.entity.field_first_name'
   *
   * @param string $entity_type_id
   *   The type of the entity for which to resolve the field name.
   * @param string $bundle
   *   The bundle of the entity for which to resolve the field name.
   * @param string $external_field_name
   *   The public field name to map to a Drupal field name.
   *
   * @return string
   *   The mapped field name.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  public function resolveInternal($entity_type_id, $bundle, $external_field_name) {
    $resource_type = $this->resourceTypeRepository->get($entity_type_id, $bundle);
    if (empty($external_field_name)) {
      throw new BadRequestHttpException('No field name was provided for the filter.');
    }

    // Turns 'uid.categories.name' into
    // 'uid.entity.field_category.entity.name'. This may be too simple, but it
    // works for the time being.
    $parts = explode('.', $external_field_name);
    $unresolved_path_parts = $parts;
    $reference_breadcrumbs = [];
    /* @var \Drupal\jsonapi\ResourceType\ResourceType[] $resource_types */
    $resource_types = [$resource_type];
    // This complex expression is needed to handle the string, "0", which would
    // otherwise be evaluated as FALSE.
    while (!is_null(($part = array_shift($parts)))) {
      $field_name = $this->getInternalName($part, $resource_types);

      // If none of the resource types are traversable, assume that the
      // remaining path parts are targeting field deltas and/or field
      // properties.
      if (!$this->resourceTypesAreTraversable($resource_types)) {
        $reference_breadcrumbs[] = $field_name;
        return $this->constructInternalPath($reference_breadcrumbs, $parts);
      }

      // Different resource types have different field definitions.
      $candidate_definitions = $this->getFieldItemDefinitions(
        $resource_types,
        $field_name
      );

      // If there are no definitions, then the field does not exist.
      if (empty($candidate_definitions)) {
        throw new BadRequestHttpException(sprintf(
          'Invalid nested filtering. The field `%s`, given in the path `%s`, does not exist.',
          $part,
          $external_field_name
        ));
      }

      // We have a valid field, so add it to the validated trail of path parts.
      $reference_breadcrumbs[] = $field_name;

      // Get all of the referenceable resource types.
      $resource_types = $this->getReferenceableResourceTypes($candidate_definitions);

      // If there are no remaining path parts, the process is finished.
      if (empty($parts)) {
        return $this->constructInternalPath($reference_breadcrumbs);
      }

      // If the next part is a delta, as in "body.0.value", then we add it to
      // the breadcrumbs and remove it from the parts that still must be
      // processed.
      if (static::isDelta($parts[0])) {
        $reference_breadcrumbs[] = array_shift($parts);
      }

      // If there are no remaining path parts, the process is finished.
      if (empty($parts)) {
        return $this->constructInternalPath($reference_breadcrumbs);
      }

      // Determine if the next part is not a property of $field_name.
      if (!static::isCandidateDefinitionProperty($parts[0], $candidate_definitions)) {
        // The next path part is neither a delta nor a field property, so it
        // must be a field on a targeted resource type. We need to guess the
        // intermediate reference property since one was not provided.
        //
        // For example, the path `uid.name` for a `node--article` resource type
        // will be resolved into `uid.entity.name`.
        $reference_breadcrumbs[] = static::getDataReferencePropertyName($candidate_definitions, $parts, $unresolved_path_parts);
      }
      else {
        // If the property is not a reference property, then all
        // remaining parts must be further property specifiers.
        // @todo: to provide a better DX, we should actually validate that the
        // remaining parts are in fact valid properties.
        if (!static::isCandidateDefinitionReferenceProperty($parts[0], $candidate_definitions)) {
          return $this->constructInternalPath($reference_breadcrumbs, $parts);
        }
        // The property is a reference, so add it to the breadcrumbs and
        // continue resolving fields.
        $reference_breadcrumbs[] = array_shift($parts);
      }
    }

    // Reconstruct the full path to the final reference field.
    return $this->constructInternalPath($reference_breadcrumbs);
  }

  /**
   * Expands the internal path with the "entity" keyword.
   *
   * @param string[] $references
   *   The resolved internal field names of all entity references.
   * @param string[] $property_path
   *   (optional) A sub-property path for the last field in the path.
   *
   * @return string
   *   The expanded and imploded path.
   */
  protected function constructInternalPath(array $references, array $property_path = []) {
    // Reconstruct the path parts that are referencing sub-properties.
    $field_path = implode('.', $property_path);

    // This rebuilds the path from the real, internal field names that have
    // been traversed so far. It joins them with the "entity" keyword as
    // required by the entity query system.
    $entity_path = implode('.', $references);

    // Reconstruct the full path to the final reference field.
    return (empty($field_path)) ? $entity_path : $entity_path . '.' . $field_path;
  }

  /**
   * Get all item definitions from a set of resources types by a field name.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $resource_types
   *   The resource types on which the field might exist.
   * @param string $field_name
   *   The field for which to retrieve field item definitions.
   *
   * @return \Drupal\Core\TypedData\ComplexDataDefinitionInterface[]
   *   The found field item definitions.
   */
  protected function getFieldItemDefinitions(array $resource_types, $field_name) {
    return array_reduce($resource_types, function ($result, $resource_type) use ($field_name) {
      /* @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
      $entity_type = $resource_type->getEntityTypeId();
      $bundle = $resource_type->getBundle();
      $definitions = $this->fieldManager->getFieldDefinitions($entity_type, $bundle);
      if (isset($definitions[$field_name])) {
        $result[] = $definitions[$field_name]->getItemDefinition();
      }
      return $result;
    }, []);
  }

  /**
   * Resolves the internal field name based on a collection of resource types.
   *
   * @param string $field_name
   *   The external field name.
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $resource_types
   *   The resource types from which to get an internal name.
   *
   * @return string
   *   The resolved internal name.
   */
  protected function getInternalName($field_name, array $resource_types) {
    return array_reduce($resource_types, function ($carry, ResourceType $resource_type) use ($field_name) {
      if ($carry != $field_name) {
        // We already found the internal name.
        return $carry;
      }
      return $resource_type->getInternalName($field_name);
    }, $field_name);
  }

  /**
   * Get the referenceable ResourceTypes for a set of field definitions.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface[] $definitions
   *   The resource types on which the reference field might exist.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType[]
   *   The referenceable target resource types.
   */
  protected function getReferenceableResourceTypes(array $definitions) {
    return array_reduce($definitions, function ($result, $definition) {
      $resource_types = array_filter(
        $this->collectResourceTypesForReference($definition)
      );
      $type_names = array_map(function ($resource_type) {
        /* @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
        return $resource_type->getTypeName();
      }, $resource_types);
      return array_merge($result, array_combine($type_names, $resource_types));
    }, []);
  }

  /**
   * Build a list of resource types depending on which bundles are referenced.
   *
   * @param \Drupal\Core\Field\TypedData\FieldItemDataDefinition $item_definition
   *   The reference definition.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType[]
   *   The list of resource types.
   *
   * @todo Add PHP type hint, see
   *   https://www.drupal.org/project/jsonapi/issues/2933895
   */
  protected function collectResourceTypesForReference(FieldItemDataDefinition $item_definition) {
    $main_property_definition = $item_definition->getPropertyDefinition(
      $item_definition->getMainPropertyName()
    );

    // Check if the field is a flavor of an Entity Reference field.
    if (!$main_property_definition instanceof DataReferenceTargetDefinition) {
      return [];
    }
    $entity_type_id = $item_definition->getSetting('target_type');
    $handler_settings = $item_definition->getSetting('handler_settings');

    $has_target_bundles = isset($handler_settings['target_bundles']) && !empty($handler_settings['target_bundles']);
    $target_bundles = $has_target_bundles ?
      $handler_settings['target_bundles']
      : $this->getAllBundlesForEntityType($entity_type_id);

    return array_map(function ($bundle) use ($entity_type_id) {
      return $this->resourceTypeRepository->get($entity_type_id, $bundle);
    }, $target_bundles);
  }

  /**
   * Whether the given resources can be traversed to other resources.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $resource_types
   *   The resources types to evaluate.
   *
   * @return bool
   *   TRUE if any one of the given resource types is traversable.
   *
   * @todo This class shouldn't be aware of entity types and their definitions.
   * Whether a resource can have relationships to other resources is information
   * we ought to be able to discover on the ResourceType. However, we cannot
   * reliably determine this information with existing APIs. Entities may be
   * backed by various storages that are unable to perform queries across
   * references and certain storages may not be able to store references at all.
   */
  protected function resourceTypesAreTraversable(array $resource_types) {
    foreach ($resource_types as $resource_type) {
      $entity_type_definition = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
      if ($entity_type_definition->entityClassImplements(FieldableEntityInterface::class)) {
        return TRUE;
      }
    }
    return FALSE;
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

  /**
   * Determines the reference property name from the given field definitions.
   *
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions specified by the path.
   * @param string[] $remaining_parts
   *   The remaining path parts.
   * @param string[] $unresolved_path_parts
   *   The unresolved path parts.
   *
   * @return string
   *   The reference name.
   */
  protected static function getDataReferencePropertyName(array $candidate_definitions, array $remaining_parts, array $unresolved_path_parts) {
    $reference_property_names = array_reduce($candidate_definitions, function (array $reference_property_names, ComplexDataDefinitionInterface $definition) {
      $property_definitions = $definition->getPropertyDefinitions();
      foreach ($property_definitions as $property_name => $property_definition) {
        if ($property_definition instanceof DataReferenceDefinitionInterface) {
          $target_definition = $property_definition->getTargetDefinition();
          assert($target_definition instanceof EntityDataDefinitionInterface, 'Entity reference fields should only be able to reference entities.');
          $reference_property_names[] = $property_name . ':' . $target_definition->getEntityTypeId();
        }
      }
      return $reference_property_names;
    }, []);
    $unique_reference_names = array_unique($reference_property_names);
    if (count($unique_reference_names) > 1) {
      $choices = array_map(function ($reference_name) use ($unresolved_path_parts, $remaining_parts) {
        $prior_parts = array_slice($unresolved_path_parts, 0, count($unresolved_path_parts) - count($remaining_parts));
        return implode('.', array_merge($prior_parts, [$reference_name], $remaining_parts));
      }, $unique_reference_names);
      // @todo Add test coverage for this in https://www.drupal.org/project/jsonapi/issues/2971281
      $message = sprintf('Ambiguous path. Try one of the following: %s, in place of the given path: %s', implode(', ', $choices), implode('.', $unresolved_path_parts));
      throw new BadRequestHttpException($message);
    }
    return $unique_reference_names[0];
  }

  /**
   * Determines if a path part targets a specific field delta.
   *
   * @param string $part
   *   The path part.
   *
   * @return bool
   *   TRUE if the part is an integer, FALSE otherwise.
   */
  protected static function isDelta($part) {
    return (bool) preg_match('/^[0-9]+$/', $part);
  }

  /**
   * Determines if a path part targets a field property, not a subsequent field.
   *
   * @param string $part
   *   The path part.
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions which are specified by the
   *   path.
   *
   * @return bool
   *   TRUE if the part is a property of one of the candidate definitions, FALSE
   *   otherwise.
   */
  protected static function isCandidateDefinitionProperty($part, array $candidate_definitions) {
    $part = static::getPathPartPropertyName($part);
    foreach ($candidate_definitions as $definition) {
      if ($definition->getPropertyDefinition($part)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Determines if a path part targets a reference property.
   *
   * @param string $part
   *   The path part.
   * @param \Drupal\Core\TypedData\ComplexDataDefinitionInterface[] $candidate_definitions
   *   A list of targeted field item definitions which are specified by the
   *   path.
   *
   * @return bool
   *   TRUE if the part is a property of one of the candidate definitions, FALSE
   *   otherwise.
   */
  protected static function isCandidateDefinitionReferenceProperty($part, array $candidate_definitions) {
    $part = static::getPathPartPropertyName($part);
    foreach ($candidate_definitions as $definition) {
      $property = $definition->getPropertyDefinition($part);
      if ($property && $property instanceof DataReferenceDefinitionInterface) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets the property name from an entity typed or untyped path part.
   *
   * A path part may contain an entity type specifier like `entity:node`. This
   * extracts the actual property name. If an entity type is not specified, then
   * the path part is simply returned. For example, both `foo` and `foo:bar`
   * will return `foo`.
   *
   * @param string $part
   *   A path part.
   *
   * @return string
   *   The property name from a path part.
   */
  protected static function getPathPartPropertyName($part) {
    return strpos($part, ':') !== FALSE ? explode(':', $part)[0] : $part;
  }

}
