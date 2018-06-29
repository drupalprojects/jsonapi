<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\jsonapi\Normalizer\Value\NullFieldNormalizerValue;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\JsonApiResource\EntityCollection;

/**
 * Normalizer class specific for entity reference field objects.
 *
 * @internal
 */
class EntityReferenceFieldNormalizer extends FieldNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityReferenceFieldItemListInterface::class;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Instantiates a EntityReferenceFieldNormalizer object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON API resource type repository.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, EntityRepositoryInterface $entity_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field */

    $field_access = $field->access('view', $context['account'], TRUE);
    if (!$field_access->isAllowed()) {
      return new NullFieldNormalizerValue($field_access, 'relationships');
    }

    // Build the relationship object based on the Entity Reference and normalize
    // that object instead.
    $main_property = $field->getItemDefinition()->getMainPropertyName();
    $definition = $field->getFieldDefinition();
    $cardinality = $definition
      ->getFieldStorageDefinition()
      ->getCardinality();
    $entity_list_metadata = [];
    $entity_list = [];
    foreach ($field as $item) {
      // A non-empty entity reference field that refers to a non-existent entity
      // is not a data integrity problem. For example, Term entities' "parent"
      // entity reference field uses target_id zero to refer to the non-existent
      // "<root>" term.
      if (!$item->isEmpty() && $item->get('entity')->getValue() === NULL) {
        $entity_list[] = NULL;
        $entity_list_metadata[] = [
          'links' => [
            'help' => [
              'href' => 'https://www.drupal.org/docs/8/modules/json-api/core-concepts#virtual',
              'meta' => [
                'about' => "Usage and meaning of the 'virtual' resource identifier.",
              ],
            ],
          ],
        ];
        continue;
      }

      // Prepare a list of additional properties stored by the field.
      $metadata = [];
      /** @var \Drupal\Core\TypedData\TypedDataInterface[] $properties */
      // @todo Remove this when JSON API requires Drupal 8.5 or newer.
      $properties = (floatval(\Drupal::VERSION) < 8.5)
        ? $item->getProperties()
        : TypedDataInternalPropertiesHelper::getNonInternalProperties($item);
      foreach ($properties as $property_key => $property) {
        if ($property_key !== $main_property) {
          $metadata[$property_key] = $this->serializer->normalize($property, $format, $context);
        }
      }
      $entity_list_metadata[] = $metadata;

      // Get the referenced entity.
      $entity = $item->get('entity')->getValue();

      if ($this->isInternalResourceType($entity)) {
        continue;
      }

      // And get the translation in the requested language.
      $entity_list[] = $this->entityRepository->getTranslationFromContext($entity);
    }
    $entity_collection = new EntityCollection($entity_list);
    $relationship = new Relationship($this->resourceTypeRepository, $field->getName(), $entity_collection, $field->getEntity(), $field_access, $cardinality, $main_property, $entity_list_metadata);
    return $this->serializer->normalize($relationship, $format, $context);
  }

  /**
   * Determines if the given entity is of an internal resource type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check the internal status.
   *
   * @return bool
   *   TRUE if the entity's resource type is internal, FALSE otherwise.
   */
  protected function isInternalResourceType(EntityInterface $entity) {
    return ($resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    )) && $resource_type->isInternal();
  }

}
