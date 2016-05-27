<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class ContentEntityNormalizer extends NormalizerBase implements ContentEntityNormalizerInterface {

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
   * @var \Drupal\jsonapi\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The resource manager.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The config resource manager.
   * @param \Drupal\jsonapi\LinkManager\LinkManagerInterface $link_manager
   *   The link manager.
   */
  public function __construct(ResourceManagerInterface $resource_manager, LinkManagerInterface $link_manager) {
    $this->resourceManager = $resource_manager;
    $this->linkManager = $link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    // If the fields to use were specified, only output those field values.
    $resource_type = $context['resource_config']->getTypeName();
    if (!empty($context['sparse_fieldset'][$resource_type])) {
      $field_names = $context['sparse_fieldset'][$resource_type];
    }
    else {
      $field_names = $this->getFieldNames($entity);
    }
    /* @var Value\FieldNormalizerValueInterface[] $normalizer_values */
    $normalizer_values = [];
    foreach ($this->getFields($entity) as $field_name => $field) {
      // Relationships cannot be excluded by using sparse fieldsets.
      $is_relationship = $this->isRelationship($field);
      if (!$is_relationship && !in_array($field_name, $field_names)) {
        continue;
      }
      $normalizer_values[$field_name] = $this->serializeField($field, $context, $format);
    }
    // Clean all the NULL values coming from denied access.
    $normalizer_values = array_filter($normalizer_values);

    $link_context = ['link_manager' => $this->linkManager];
    return new Value\ContentEntityNormalizerValue($normalizer_values, $context, $entity, $link_context);
  }

  /**
   * Checks if the passed field is a relationship field.
   *
   * @param mixed $field
   *   The field.
   *
   * @return bool
   *   TRUE if it's a JSON API relationship.
   */
  protected function isRelationship($field) {
    return $field instanceof EntityReferenceFieldItemList;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    throw new \Exception('Denormalization not implemented for JSON API');
  }

  /**
   * Gets the field names for the given entity.
   *
   * @param mixed $entity
   *   The entity.
   *
   * @return string[]
   *   The field names.
   */
  protected function getFieldNames($entity) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    return array_keys($this->getFields($entity));
  }

  /**
   * Gets the field names for the given entity.
   *
   * @param mixed $entity
   *   The entity.
   *
   * @return array
   *   The fields.
   */
  protected function getFields($entity) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    return $entity->getFields();
  }


  /**
   * Serializes a given field.
   *
   * @param mixed $field
   *   The field to serialize.
   * @param array $context
   *   The normalization context.
   * @param string $format
   *   The serialization format.
   *
   * @return Value\FieldNormalizerValueInterface
   *   The normalized value.
   */
  protected function serializeField($field, $context, $format) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field */
    // Continue if the current user does not have access to view this field.
    if (!$field->access('view', $context['account'])) {
      return NULL;
    }
    $output = $this->serializer->normalize($field, $format, $context);
    $is_relationship = $this->isRelationship($field);
    $property_type = $is_relationship ? 'relationships' : 'attributes';
    $output->setPropertyType($property_type);
    return $output;
  }

}
