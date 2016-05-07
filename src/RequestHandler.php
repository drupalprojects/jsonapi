<?php

/**
 * @file
 * Contains \Drupal\jsonapi\RequestHandler.
 */

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
    $output = $this->container->get('renderer')
      ->executeInRenderContext($context, function () use ($serializer, $data, $format, $request) {
        $context = [];
        if ($fields_param = $request->query->get('fields')) {
          $context['sparse_fieldset'] = explode(',', $fields_param);
        }
        return $serializer->serialize($data, $format, $context);
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

}
