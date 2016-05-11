<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class ContentEntityNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ContentEntityInterface::class;

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = array('api_json');

  /**
   * The link manager.
   *
   * @var LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\rest\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LinkManagerInterface $link_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->linkManager = $link_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    $normalizer_entity = $this->buildNormalizerValue($entity, $format, $context);
    $normalized = $normalizer_entity->rasterizeValue();
    $normalized['included'] = array_values($normalizer_entity->rasterizeIncludes());
    $normalized['included'] = array_filter($normalized['included']);
    return $normalized;
  }

  /**
   * @todo Move to an interface.
   */
  public function buildNormalizerValue($entity, $format = NULL, array $context = array()) {
    /* @var $entity \Drupal\Core\Entity\ContentEntityInterface */
    $context += array(
      'account' => NULL,
      'sparse_fieldset' => NULL,
      'resource_path' => NULL,
    );

    // If the fields to use were specified, only output those field values.
    if (!empty($context['sparse_fieldset'][$context['resource_path']])) {
      $fields_names = $context['sparse_fieldset'][$context['resource_path']];
    }
    else {
      $fields_names = array_map(function ($field) {
        /* @var \Drupal\Core\Field\FieldItemListInterface $field */
        return $field->getName();
      }, $entity->getFields());
    }
    /* @var Value\FieldNormalizerValueInterface[] $normalizer_values */
    $normalizer_values = [];
    foreach ($entity->getFields() as $field) {
      // Continue if the current user does not have access to view this field.
      if (!$field->access('view', $context['account'])) {
        continue;
      }

      // Relationships cannot be excluded by using sparse fieldsets.
      $is_relationship = $this->isRelationship($field);
      $field_name = $field->getName();
      if (!$is_relationship && !in_array($field_name, $fields_names)) {
        continue;
      }
      $normalizer_values[$field_name] = $this->serializer->normalize($field, $format, $context);

      $property_type = $is_relationship ? 'relationships' : 'attributes';
      $normalizer_values[$field_name]->setPropertyType($property_type);
    }

    return new Value\ContentEntityNormalizerValue($normalizer_values, $context, $entity, $this->linkManager, $this->entityTypeManager);
  }

  /**
   * Checks if the passed field is a relationship field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field.
   *
   * @return bool
   *   TRUE if it's a JSON API relationship.
   */
  protected function isRelationship(FieldItemListInterface $field) {
    if (!$field instanceof EntityReferenceFieldItemList) {
      return FALSE;
    }
    $target_type_id = $field->getItemDefinition()->getSetting('target_type');
    $entity_type = $this->entityTypeManager->getDefinition($target_type_id);
    return $entity_type instanceof ContentEntityTypeInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    throw new \Exception('Denormalization not implemented for JSON API');
  }

}
