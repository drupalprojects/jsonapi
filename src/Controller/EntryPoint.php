<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class EntryPoint extends ControllerBase {

  /**
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * @var \Drupal\Core\Cache\CacheableResponseInterface
   */
  protected $response;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * EntryPoint constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Cache\CacheableJsonResponse $response
   */
  public function __construct(ResourceTypeRepository $resource_type_repository, RendererInterface $renderer, CacheableJsonResponse $response) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->renderer = $renderer;
    $this->response = $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->get('renderer'),
      new CacheableJsonResponse()
    );
  }

  /**
   * Controller to list all the resources.
   */
  public function index() {
    // Execute the request in context so the cacheable metadata from the entity
    // grants system is caught and added to the response. This is surfaced when
    // executing the underlying entity query.
    $context = new RenderContext();
    /** @var \Drupal\Core\Cache\CacheableResponseInterface $response */
    $do_build_urls = function () {
      $self = Url::fromRoute('jsonapi.resource_list')
        ->setOption('absolute', TRUE);

      return array_reduce($this->resourceTypeRepository->all(), function (array $carry, ResourceType $resource_type) {
        // TODO: Learn how to invalidate the cache for this page when a new entity
        // type or bundle gets added, removed or updated.
        // $this->response->addCacheableDependency($definition);
        $url = Url::fromRoute(sprintf('jsonapi.%s.collection', $resource_type->getTypeName()));
        $url->setOption('absolute', TRUE);
        $carry[$resource_type->getTypeName()] = $url->toString();

        return $carry;
      }, ['self' => $self->toString()]);
    };
    $urls = $this->renderer->executeInRenderContext($context, $do_build_urls);
    if (!$context->isEmpty()) {
      $this->response->addCacheableDependency($context->pop());
    }

    $this->response->setData(
      [
        'data' => [],
        'links' => $urls
      ]
    );
    return $this->response;
  }

}
