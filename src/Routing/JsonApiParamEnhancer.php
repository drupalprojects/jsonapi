<?php

namespace Drupal\jsonapi\Routing;
use Drupal\Core\Routing\Enhancer\RouteEnhancerInterface;
use Drupal\jsonapi\Routing\Param\CursorPage;
use Drupal\jsonapi\Routing\Param\Filter;
use Drupal\jsonapi\Routing\Param\Sort;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;


/**
 * Class JsonApiParamEnhancer.
 *
 * @package Drupal\jsonapi\Routing
 */
class JsonApiParamEnhancer implements RouteEnhancerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    // This enhancer applies to the JSON API routes.
    return $route->getDefault('_controller') == Routes::FRONT_CONTROLLER;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $options = [];
    if ($request->query->has('filter')) {
      $options['filter'] = new Filter($request->query->get('filter'));
    }
    if ($request->query->has('sort')) {
      $options['sort'] = new Sort($request->query->get('sort'));
    }
    if ($request->query->has('page')) {
      $options['page'] = new CursorPage($request->query->get('page'), 50);
    }
    $defaults['_json_api_params'] = $options;
    return $defaults;
  }


}
