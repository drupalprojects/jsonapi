<?php

namespace Drupal\jsonapi\LinkManager;

use Drupal\Core\Url;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LinkManager.
 *
 * @package Drupal\jsonapi
 */
class LinkManager implements LinkManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityLink($entity_id, ResourceConfigInterface $resource_config, array $route_parameters, $key) {
    $route_parameters += [
      $resource_config->getEntityTypeId() => $entity_id,
    ];
    $prefix = $resource_config->getGlobalConfig()->get('prefix');
    $route_key = sprintf('%s.dynamic.%s.%s', $prefix, $resource_config->getTypeName(), $key);
    $url = Url::fromRoute($route_key, $route_parameters, ['absolute' => TRUE]);
    return $url->setRouteParameter('_format', 'api_json')->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestLink(Request $request) {
    return Url::createFromRequest($request)
      ->setOption('absolute', TRUE)
      ->setOption('query', (array) $request->query->getIterator())
      ->toString();
  }

}
