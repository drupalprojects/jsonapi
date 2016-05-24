<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Drupal\jsonapi\EntityCollection;
use Drupal\jsonapi\Resource\DocumentWrapperInterface;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DocumentRootNormalizer.
 *
 * @package Drupal\jsonapi\Normalizer
 */
class DocumentRootNormalizer extends NormalizerBase implements DocumentRootNormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = DocumentWrapperInterface::class;

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
   * The resource manager.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\rest\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager
   *   The config resource manager.
   */
  public function __construct(LinkManagerInterface $link_manager, EntityTypeManagerInterface $entity_type_manager, ResourceManagerInterface $resource_manager) {
    $this->linkManager = $link_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceManager = $resource_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    throw new \Exception('Denormalization not implemented for JSON API');
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $value_extractor = $this->buildNormalizerValue($object->getData(), $format, $context);
    $normalized = $value_extractor->rasterizeValue();
    $included = array_filter($value_extractor->rasterizeIncludes());
    if (!empty($included)) {
      $normalized['included'] = $included;
    }
    return $normalized;
  }

  /**
   * Build the normalizer value.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\ContentEntityNormalizerValueInterface
   *   The normalizer value.
   */
  public function buildNormalizerValue($data, $format = NULL, array $context = array()) {
    if ($data instanceof EntityReferenceFieldItemListInterface) {
      return $this->serializer->normalize($data, $format, $context);
    }
    else {
      $is_collection = $data instanceof EntityCollection;
      // To improve the logical workflow deal with an array at all times.
      $entities = $is_collection ? $data->toArray() : [$data];
      // Use the first entity to extract the entity type and bundle from it.
      $context += $this->expandContext($entities[0], $context['request']);
      $serializer = $this->serializer;
      $normalizer_values = array_map(function ($entity) use ($format, $context, $serializer) {
        return $serializer->normalize($entity, $format, $context);
      }, $entities);
    }

    return new Value\DocumentRootNormalizerValue($normalizer_values, $context, $is_collection, $entities, $this->linkManager, $this->entityTypeManager);
  }

  /**
   * Expand the context information based on the request.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to normalize.
   * @param Request $request
   *   The request.
   *
   * @return array
   *   The expanded context.
   */
  protected function expandContext(EntityInterface $entity, Request $request) {
    $resource_config = $this->resourceManager->get($entity->getEntityTypeId(), $entity->bundle());
    $context = array(
      'account' => NULL,
      'sparse_fieldset' => NULL,
      'resource_config' => $resource_config,
      'include' => array_filter(explode(',', $request->query->get('include'))),
    );
    if ($fields_param = $request->query->get('fields')) {
      $context['sparse_fieldset'] = array_map(function ($item) {
        return explode(',', $item);
      }, $request->query->get('fields'));
    }
    return $context;
  }

}
