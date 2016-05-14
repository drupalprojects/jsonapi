<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;

/**
 * Class ContentEntityNormalizerValue.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
class ContentEntityNormalizerValue implements ContentEntityNormalizerValueInterface {

  /**
   * The values.
   *
   * @param array
   */
  protected $values;

  /**
   * The includes.
   *
   * @param array
   */
  protected $includes;

  /**
   * The resource path.
   *
   * @param array
   */
  protected $context;

  /**
   * The resource entity.
   *
   * @param EntityInterface
   */
  protected $entity;

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
   * Instantiate a ContentEntityNormalizerValue object.
   *
   * @param FieldNormalizerValueInterface[] $values
   *   The normalized result.
   */
  public function __construct(array $values, array $context, EntityInterface $entity, LinkManagerInterface $link_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->values = $values;
    $this->context = $context;
    $this->entity = $entity;
    $this->linkManager = $link_manager;
    $this->entityTypeManager = $entity_type_manager;
    // Get an array of arrays of includes.
    $this->includes = array_map(function ($value) {
      return $value->getIncludes();
    }, $values);
    // Flatten the includes.
    $this->includes = array_reduce($this->includes, function ($carry, $includes) {
      return array_merge($carry, $includes);
    }, []);
    // Filter the empty values.
    $this->includes = array_filter($this->includes);
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeValue() {
    // Create the array of normalized fields, starting with the URI.
    $rasterized = [
      'type' => $this->context['resource_config']->getTypeName(),
      'id' => $this->entity->id(),
      'attributes' => [],
      'relationships' => [],
      'links' => [
        'self' => $this->getEntityUri($this->entity),
        'type' => $this->linkManager->getTypeUri(
          $this->entity->getEntityTypeId(),
          $this->entity->bundle(), $this->context
        ),
      ],
    ];

    foreach ($this->getValues() as $field_name => $normalizer_value) {
      $rasterized[$normalizer_value->getPropertyType()][$field_name] = $normalizer_value->rasterizeValue();
    }
    return array_filter($rasterized);
  }

  /**
   * {@inheritdoc}
   */
  public function rasterizeIncludes() {
    // First gather all the includes in the chain.
    return array_map(function ($include) {
      return $include->rasterizeValue();
    }, $this->getIncludes());
  }

  /**
   * {@inheritdoc}
   */
  public function getValues() {
    return $this->values;
  }

  /**
   * Gets a flattened list of includes in all the chain.
   *
   * @return ContentEntityNormalizerValueInterface
   *   The array of included relationships.
   */
  public function getIncludes() {
    $nested_includes = array_map(function ($include) {
      return $include->getIncludes();
    }, $this->includes);
    return array_reduce(array_filter($nested_includes), function ($carry, $item) {
      return array_merge($carry, $item);
    }, $this->includes);
  }

  /**
   * Constructs the entity URI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The entity URI.
   */
  protected function getEntityUri(EntityInterface $entity) {
    // Some entity types don't provide a canonical link template, at least call
    // out to ->url().
    if ($entity->isNew() || !$entity->hasLinkTemplate('canonical')) {
      return $entity->url('canonical', []);
    }
    $url = $entity->toUrl('canonical', ['absolute' => TRUE]);
    return $url->setRouteParameter('_format', 'api_json')->toString();
  }

}
