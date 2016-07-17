<?php

namespace Drupal\jsonapi_docson\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\jsonapi\Configuration\ResourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class DocsonController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The resource manager interface.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

  /**
   * Instantiates a Routes object.
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
  public static function create(ContainerInterface $container) {
    /* @var \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager */
    $resource_manager = $container->get('jsonapi.resource.manager');

    return new static($resource_manager);
  }

  public function listResources() {
    $build = [
      '#type' => 'table',
      '#header' => [$this->t('Resource'), $this->t('Schema')],
    ];

    foreach ($this->resourceManager->all() as $resource_id => $resource) {
      $global_config = $resource->getGlobalConfig();
      $prefix = $global_config->get('prefix');
      $schema_prefix = $global_config->get('schema_prefix');
      $partial_path = '/' . $prefix . $resource->getPath();
      $schema_partial_path = '/' . $schema_prefix . $resource->getPath();
      $route_key = sprintf('%s.dynamic.%s.', $prefix, $resource->getTypeName());
      $entity_type = $resource->getEntityTypeId();

      // @todo its sad that this module needs to know about the different kind
      //   of schemas we expose.
      $build[$partial_path] = [
        ['data' => ['#markup' => $partial_path]],
        ['data' => Link::createFromRoute('Schema:' . $schema_partial_path, 'jsonapi_docson.schema_inspector', ['schema' => Url::fromRoute($route_key . 'schema')->toString(), 'resource_id' => $resource_id])->toRenderable()],
      ];

      $individual_path = sprintf('%s/{%s}', $partial_path, $entity_type);
      $schema_individual_path = $schema_partial_path . '/individual';
      $build[$individual_path] = [
        ['data' => ['#markup' => $individual_path]],
        ['data' => Link::createFromRoute('Schema:' . $schema_individual_path, 'jsonapi_docson.schema_inspector', ['schema' => Url::fromRoute($route_key . 'individual.schema', [$entity_type => 'individual'])->toString(), 'resource_id' => $resource_id])->toRenderable()],
      ];
    }

    return $build;
  }

  public function inspectSchema(Request $request) {
    $schema = $request->query->get('schema');
    $resource_id = $request->query->get('resource_id');
    /* @var \Drupal\jsonapi\Configuration\ResourceConfigInterface $resource_config */
    $resource_config = empty($this->resourceManager->all()[$resource_id]) ?
      NULL :
      $this->resourceManager->all()[$resource_id];

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#attributes' => [
        'src' => '/libraries/docson/widget.js',
        'data-schema' => $schema,
      ],
    ];
    if ($resource_config) {
      $build['#title'] = $this->t('Schema for "@entity_type/@bundle"', [
        '@entity_type' => $resource_config->getEntityTypeId(),
        '@bundle' => $resource_config->getBundleId(),
      ]);
    }

    return $build;
  }

}
