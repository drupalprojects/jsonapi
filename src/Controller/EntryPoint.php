<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the API entry point.
 *
 * @internal
 */
class EntryPoint extends ControllerBase {

  /**
   * The JSON API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * EntryPoint constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, RendererInterface $renderer, AccountInterface $user) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->renderer = $renderer;
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->get('renderer'),
      $container->get('current_user')
    );
  }

  /**
   * Controller to list all the resources.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The response object.
   */
  public function index() {
    // Execute the request in context so the cacheable metadata from the entity
    // grants system is caught and added to the response. This is surfaced when
    // executing the underlying entity query.
    $context = new RenderContext();
    /** @var \Drupal\Core\Cache\CacheableResponseInterface $response */
    $do_build_urls = function () {
      $self = Url::fromRoute('jsonapi.resource_list')->setAbsolute();

      // Only build URLs for exposed resources.
      $resources = array_filter($this->resourceTypeRepository->all(), function ($resource) {
        return !$resource->isInternal();
      });

      return array_reduce($resources, function (array $carry, ResourceType $resource_type) {
        // TODO: Learn how to invalidate the cache for this page when a new
        // entity type or bundle gets added, removed or updated.
        // $this->response->addCacheableDependency($definition);
        $url = Url::fromRoute(sprintf('jsonapi.%s.collection', $resource_type->getTypeName()))
          ->setAbsolute();
        $carry[$resource_type->getTypeName()] = $url->toString();

        return $carry;
      }, ['self' => $self->toString()]);
    };
    $urls = $this->renderer->executeInRenderContext($context, $do_build_urls);

    $json_response = new CacheableJsonResponse();
    $doc = [
      'data' => [],
      'links' => $urls,
    ];
    $json_response->addCacheableDependency((new CacheableMetadata())->addCacheContexts(['user.roles:authenticated']));
    if ($this->user->isAuthenticated()) {
      $me_url = Url::fromRoute('jsonapi.user--user.individual', ['user' => User::load($this->user->id())->uuid()])
        ->setAbsolute()
        ->toString(TRUE);
      $doc['meta']['links']['me'] = $me_url->getGeneratedUrl();
      // The cacheability of the `me` URL is the cacheability of that URL itself
      // and the currently authenticated user.
      $json_response->addCacheableDependency(CacheableMetadata::createFromObject($me_url)->addCacheContexts(['user']));
    }
    $json_response->setData($doc);

    if (!$context->isEmpty()) {
      $json_response->addCacheableDependency($context->pop());
    }

    return $json_response;
  }

}
