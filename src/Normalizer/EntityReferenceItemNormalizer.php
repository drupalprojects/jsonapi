<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';

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
    $normalizer_value = new Value\EntityReferenceItemNormalizerValue($values, $this->getResourcePath($target_entity));
    // If this is not a content entity, let the parent implementation handle it,
    // only content entities are supported as embedded resources.
    if (!($target_entity instanceof FieldableEntityInterface)) {
      return $normalizer_value;
    }
    // TODO Only include if the target entity type has the resource enabled.
    if (!empty($context['include']) && in_array($field_item->getParent()->getName(), $context['include'])) {
      $context = $this->buildSubContext($context, $target_entity, $field_item->getParent()
        ->getName());
      $entity_normalizer = $this->container->get('serializer.normalizer.entity.jsonapi');
      $normalizer_value->setInclude($entity_normalizer->buildNormalizerValue($target_entity, $format, $context));
    }
    return $normalizer_value;
  }

  /**
   * Get the resource path from the content entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity.
   *
   * @return string
   *   The resource path.
   */
  protected function getResourcePath(EntityInterface $entity) {
    $resource = $this->container->get('plugin.manager.rest')
      ->createInstance('entity:' . $entity->getEntityTypeId());
    $definition = $resource->getPluginDefinition();
    $path_template = $definition['uri_paths']['canonical'];
    return trim(preg_replace('/\{.*\}/', '', $path_template), '/');
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
    $context['resource_path'] = $this->getResourcePath($entity);
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
