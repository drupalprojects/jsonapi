<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
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
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = array('api_json');

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\rest\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   */
  public function __construct(LinkManagerInterface $link_manager) {
    $this->linkManager = $link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    $context += array(
      'account' => NULL,
      'sparse_fieldset' => NULL,
    );

    // Create the array of normalized fields, starting with the URI.
    /* @var $entity \Drupal\Core\Entity\ContentEntityInterface */
    $normalized = [
      'data' => [
        'attributes' => [],
      ],
      'links' => [
        'self' => $this->getEntityUri($entity),
        'type' => $this->linkManager->getTypeUri($entity->getEntityTypeId(), $entity->bundle(), $context),
      ],
    ];

    // If the fields to use were specified, only output those field values.
    if (isset($context['sparse_fieldset'])) {
      $fields_names = $context['sparse_fieldset'];
    }
    else {
      $fields_names = array_map(function ($field) {
        /* @var \Drupal\Core\Field\FieldItemListInterface $field */
        return $field->getName();
      }, $entity->getFields());
    }
    foreach ($entity->getFields() as $field) {
      // Continue if the current user does not have access to view this field.
      if (!$field->access('view', $context['account'])) {
        continue;
      }

      // Relationships cannot be excluded by using sparse fieldsets.
      $is_relationship = $field instanceof EntityReferenceFieldItemList;
      $field_name = $field->getName();
      if (!$is_relationship && !in_array($field_name, $fields_names)) {
        continue;
      }
      $normalized_property = $this
        ->serializer
        ->normalize($field, $format, $context);
      $bucket = $is_relationship ?
        'relationships' :
        'attributes';
      $normalized['data'][$bucket][$field_name] = $normalized_property;
    }

    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    throw new \Exception('Denormalization not implemented for JSON API');
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
