<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;
use Drupal\jsonapi\EntityCollection;
use Drupal\jsonapi\Resource\DocumentWrapperInterface;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class DocumentRootNormalizer.
 *
 * @package Drupal\jsonapi\Normalizer
 */
class DocumentRootNormalizer extends NormalizerBase implements DenormalizerInterface, DocumentRootNormalizerInterface {

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
    $context['resource_config'] = $this->currentContext->getResourceConfig();
    $normalized = $data['data']['attributes'];
    $normalized = array_merge($normalized, array_map(function ($relationship) {
      return $relationship['data']['id'];
    }, $data['data']['relationships']));
    return $this->serializer->denormalize(
      $normalized,
      $context['resource_config']->getDeserializationTargetClass(),
      'api_json',
      $context
    );
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
      $context += $this->expandContext($context['request']);
      $serializer = $this->serializer;
      $normalizer_values = array_map(function ($entity) use ($format, $context, $serializer) {
        return $serializer->normalize($entity, $format, $context);
      }, $entities);
    }

    return new Value\DocumentRootNormalizerValue($normalizer_values, $context, $is_collection, [
      'link_manager' => $this->linkManager,
    ]);
  }

  /**
   * Expand the context information based on the current request context.
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
