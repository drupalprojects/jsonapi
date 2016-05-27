<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\serialization\EntityResolver\UuidReferenceInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Converts the Drupal entity reference item object to HAL array structure.
 */
class EntityReferenceItemNormalizer extends FieldItemNormalizer implements UuidReferenceInterface {

  use ContainerAwareTrait;

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = EntityReferenceItem::class;

  /**
   * The manager for resource configuration.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * Instantiates a EntityReferenceItemNormalizer object.
   *
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The resource manager.
   */
  public function __construct(ResourceManagerInterface $resource_manager) {
    $this->resourceManager = $resource_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = array()) {
    /* @var $field_item \Drupal\Core\Field\FieldItemInterface */
    $target_entity = $field_item->get('entity')->getValue();
    $values = $field_item->toArray();
    $main_property = $field_item->mainPropertyName();
    $values = [$main_property => $values[$main_property]];
    if (isset($context['langcode'])) {
      $values['lang'] = $context['langcode'];
    }
    $normalizer_value = new Value\EntityReferenceItemNormalizerValue(
      $values,
      $this->resourceManager
        ->get($target_entity->getEntityTypeId(), $target_entity->bundle())
        ->getTypeName()
    );

    // TODO Only include if the target entity type has the resource enabled.
    if (!empty($context['include']) && in_array($field_item->getParent()
        ->getName(), $context['include'])
    ) {
      $context = $this->buildSubContext($context, $target_entity, $field_item->getParent()
        ->getName());
      $entity_normalizer = $this->container->get('serializer.normalizer.document_root.jsonapi');
      $normalizer_value->setInclude($entity_normalizer->buildNormalizerValue($target_entity, $format, $context));
    }
    return $normalizer_value;
  }

  /**
   * Builds the sub-context for the relationship include.
   *
   * @param array $context
   *   The serialization context.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The related entity.
   * @param string $host_field_name
   *   The name of the field reference.
   *
   * @return array
   *   The modified new context.
   */
  protected function buildSubContext($context, EntityInterface $entity, $host_field_name) {
    // Swap out the context for the context of the referenced resource.
    $context['resource_config'] = $this->resourceManager
      ->get($entity->getEntityTypeId(), $entity->bundle());
    // Since we're going one level down the only includes we need are the ones
    // that apply to this level as well.
    $include_candidates = array_filter($context['include'], function ($include) use ($host_field_name) {
      return strpos($include, $host_field_name . '.') === 0;
    });
    $context['include'] = array_map(function ($include) use ($host_field_name) {
      return str_replace($host_field_name . '.', '', $include);
    }, $include_candidates);
    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid($data) {
    if (isset($data['uuid'])) {
      return NULL;
    }
    $uuid = $data['uuid'];
    // The value may be a nested array like $uuid[0]['value'].
    if (is_array($uuid) && isset($uuid[0]['value'])) {
      $uuid = $uuid[0]['value'];
    }
    return $uuid;
  }

}
