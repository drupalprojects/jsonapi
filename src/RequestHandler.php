<?php

namespace Drupal\jsonapi;

use Drupal\Core\Render\RenderContext;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class RequestHandler.
 *
 * @package Drupal\jsonapi
 */
class RequestHandler extends \Drupal\rest\RequestHandler {

  /**
   * {@inheritdoc}
   *
   * This needs a patch to Drupal core's REST system.
   *
   * @see https://www.drupal.org/node/2718545
   */
  protected function renderResponse(Request $request, ResourceResponse $response, SerializerInterface $serializer, $format) {
    $data = $response->getResponseData();
    $context = new RenderContext();
    $normalizer_context = [
      'resource_path' => $this->resourcePath($request),
    ];
    if ($fields_param = $request->query->get('fields')) {
      $normalizer_context['sparse_fieldset'] = array_map(function ($item) {
        return explode(',', $item);
      }, $request->query->get('fields'));
    }
    $normalizer_context['include'] = array_filter(explode(',', $fields_param = $request->query->get('include')));
    $output = $this->container->get('renderer')
      ->executeInRenderContext($context, function () use ($serializer, $data, $format, $normalizer_context) {
        return $serializer->serialize($data, $format, $normalizer_context);
      });
    $response->setContent($output);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    $response->headers->set('Content-Type', $request->getMimeType($format));
    // Add rest settings config's cache tags.
    $response->addCacheableDependency($this->container->get('config.factory')
      ->get('rest.settings'));
    $response->addCacheableDependency(new RequestCacheabilityDependency());

    return $response;
  }

  /**
   * Get the resource path for the current request.
   *
   * @param Request $request
   *   The request to examine.
   *
   * @returns string
   *   The base resource path.
   */
  protected function resourcePath(Request $request) {
    $templated_path = $request->get('_route_object')->getPath();
    return trim(preg_replace('/\{.*}/', '', $templated_path), '/');
  }

}
