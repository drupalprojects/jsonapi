<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;
use Drupal\jsonapi\Error\SerializableHttpException;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Drupal\jsonapi\Normalizer\Value\NullFieldNormalizerValue;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class EntityNormalizer extends NormalizerBase implements DenormalizerInterface {

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
   * The current JSON API request context.
   *
   * @var \Drupal\jsonapi\Context\CurrentContextInterface
   */
  protected $currentContext;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManagerInterface $link_manager
   *   The link manager.
   * @param \Drupal\jsonapi\Context\CurrentContextInterface $current_context
   *   The current context.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(LinkManagerInterface $link_manager, CurrentContextInterface $current_context, EntityTypeManagerInterface $entity_type_manager) {
    $this->linkManager = $link_manager;
    $this->currentContext = $current_context;
    $this->resourceManager = $current_context->getResourceManager();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    // If the fields to use were specified, only output those field values.
    $context['resource_config'] = $this->resourceManager->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    $resource_type = $context['resource_config']->getTypeName();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    $bundle = $context['resource_config']->getBundle();
    if (!empty($context['sparse_fieldset'][$resource_type])) {
      $field_names = $context['sparse_fieldset'][$resource_type];
    }
    else {
      $field_names = $this->getFieldNames($entity, $bundle);
    }
    /* @var Value\FieldNormalizerValueInterface[] $normalizer_values */
    $normalizer_values = [];
    foreach ($this->getFields($entity, $bundle) as $field_name => $field) {
      if (!in_array($field_name, $field_names)) {
        continue;
      }
      $normalizer_values[$field_name] = $this->serializeField($field, $context, $format);
    }

    $link_context = ['link_manager' => $this->linkManager];
    $output = new Value\EntityNormalizerValue($normalizer_values, $context, $entity, $link_context);
    // Add the entity level cacheability metadata.
    $output->addCacheableDependency($entity);
    $output->addCacheableDependency($output);
    // Add the field level cacheability metadata.
    array_walk($normalizer_values, function ($normalizer_value) {
      if ($normalizer_value instanceof RefinableCacheableDependencyInterface) {
        $normalizer_value->addCacheableDependency($normalizer_value);
      }
    });
    return $output;
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
    return $field instanceof EntityReferenceFieldItemList || $field instanceof Relationship;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    if (empty($context['resource_config']) || !$context['resource_config'] instanceof ResourceConfigInterface) {
      throw new SerializableHttpException(412, 'Missing context during denormalization.');
    }
    /* @var \Drupal\jsonapi\Configuration\ResourceConfigInterface $resource_config */
    $resource_config = $context['resource_config'];
    $entity_type_id = $resource_config->getEntityTypeId();
    $bundle = $resource_config->getBundle();
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getKey('bundle');
    if ($bundle_key && $bundle) {
      $data[$bundle_key] = $bundle;
    }

    return $this->entityTypeManager->getStorage($entity_type_id)
      ->create($data);
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
  protected function getFieldNames($entity, $bundle) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    return array_keys($this->getFields($entity, $bundle));
  }

  /**
   * Gets the field names for the given entity.
   *
   * @param mixed $entity
   *   The entity.
   * @param string $bundle
   *   The bundle id.
   *
   * @return array
   *   The fields.
   */
  protected function getFields($entity, $bundle) {
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
    /* @var \Drupal\Core\Field\FieldItemListInterface|\Drupal\jsonapi\Normalizer\Relationship $field */
    // Continue if the current user does not have access to view this field.
    $access = $field->access('view', $context['account'], TRUE);
    if ($field instanceof AccessibleInterface && !$access->isAllowed()) {
      return (new NullFieldNormalizerValue())->addCacheableDependency($access);
    }
    /** @var \Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue $output */
    $output = $this->serializer->normalize($field, $format, $context);
    $is_relationship = $this->isRelationship($field);
    $property_type = $is_relationship ? 'relationships' : 'attributes';
    $output->setPropertyType($property_type);

    if ($output instanceof RefinableCacheableDependencyInterface) {
      // Add the cache dependency to the field level object because we want to
      // allow the field normalizers to add extra cacheability metadata.
      $output->addCacheableDependency($access);
    }

    return $output;
  }

}
