<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;
use Drupal\jsonapi\EntityCollectionInterface;
use Drupal\jsonapi\Resource\DocumentWrapperInterface;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class DocumentRootNormalizer.
 *
 * @package Drupal\jsonapi\Normalizer
 */
class DocumentRootNormalizer extends NormalizerBase implements DenormalizerInterface, NormalizerInterface, DocumentRootNormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = DocumentWrapperInterface::class;

  /**
   * The link manager to get the links.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The current JSON API request context.
   *
   * @var \Drupal\jsonapi\Context\CurrentContextInterface
   */
  protected $currentContext;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManagerInterface $link_manager
   *   The link manager to get the links.
   * @param \Drupal\jsonapi\Context\CurrentContextInterface $current_context
   *   The current context.
   */
  public function __construct(LinkManagerInterface $link_manager, CurrentContextInterface $current_context) {
    $this->linkManager = $link_manager;
    $this->currentContext = $current_context;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    $context += [
      'on_relationship' => (bool) $this->currentContext->getCurrentRoute()->getDefault('_on_relationship'),
    ];
    $normalized = [];
    if (!empty($data['data']['attributes'])) {
      $normalized = $data['data']['attributes'];
    }
    if (!empty($data['data']['relationships'])) {
      // Add the relationship ids.
      $normalized = array_merge($normalized, array_map(function ($relationship) {
        return $relationship['data']['id'];
      }, $data['data']['relationships']));
    }
    // Overwrite the serialization target class with the one in the resource
    // config.
    $class = $context['resource_config']->getDeserializationTargetClass();
    return $this->serializer
      ->denormalize($normalized, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $context += ['resource_config' => $this->currentContext->getResourceConfig()];
    $value_extractor = $this->buildNormalizerValue($object->getData(), $format, $context);
    if (!empty($context['cacheable_metadata'])) {
      $context['cacheable_metadata']->addCacheableDependency($value_extractor);
    }
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
      $is_collection = $data instanceof EntityCollectionInterface;
      // To improve the logical workflow deal with an array at all times.
      $entities = $is_collection ? $data->toArray() : [$data];
      $context += $this->expandContext($context['request']);
      $context['has_next_page'] = $is_collection ? $data->hasNextPage() : FALSE;
      $serializer = $this->serializer;
      $normalizer_values = array_map(function ($entity) use ($format, $context, $serializer) {
        return $serializer->normalize($entity, $format, $context);
      }, $entities);
    }

    return new Value\DocumentRootNormalizerValue($normalizer_values, $context, $is_collection, [
      'link_manager' => $this->linkManager,
      'has_next_page' => $context['has_next_page'],
    ]);
  }

  /**
   * Expand the context information based on the current request context.
   *
   * @param Request $request
   *   The request to get the URL params from to expand the context.
   *
   * @return array
   *   The expanded context.
   */
  protected function expandContext(Request $request) {
    $context = array(
      'account' => NULL,
      'sparse_fieldset' => NULL,
      'resource_config' => NULL,
      'include' => array_filter(explode(',', $request->query->get('include'))),
    );
    if (isset($this->currentContext)) {
      $context['resource_config'] = $this->currentContext->getResourceConfig();
    }
    if ($fields_param = $request->query->get('fields')) {
      $context['sparse_fieldset'] = array_map(function ($item) {
        return explode(',', $item);
      }, $request->query->get('fields'));
    }
    return $context;
  }

}
