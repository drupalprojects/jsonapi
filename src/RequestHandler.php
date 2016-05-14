<?php

namespace Drupal\jsonapi;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Resource\EntityResource;
use Drupal\rest\RequestHandler as RestRequestHandler;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Acts as intermediate request forwarder for resource plugins.
 */
class RequestHandler extends RestRequestHandler {

  /**
   * Handles a web API request.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function handle(RouteMatchInterface $route_match, Request $request) {
    $method = strtolower($request->getMethod());
    $route = $route_match->getRouteObject();

    // Deserialize incoming data if available.
    /* @var \Symfony\Component\Serializer\SerializerInterface $serializer */
    $serializer = $this->container->get('serializer');
    $unserialized = $this->deserializeBody($request, $serializer, $route->getOption('serialization_class'));

    // Determine the request parameters that should be passed to the resource
    // plugin.
    $route_parameters = $route_match->getParameters();
    $parameters = array();
    // Filter out all internal parameters starting with "_".
    foreach ($route_parameters as $key => $parameter) {
      if ($key{0} !== '_') {
        $parameters[] = $parameter;
      }
    }

    // Invoke the operation on the resource plugin.
    // All REST routes are restricted to exactly one format, so instead of
    // parsing it out of the Accept headers again, we can simply retrieve the
    // format requirement. If there is no format associated, just pick JSON.
    $format = 'api_json';
    $action = $this->action($route_match, $method);
    /** @var \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager */
    $resource_manager = $this->container->get('jsonapi.resource.manager');
    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $resource = new EntityResource($resource_manager->get(
      $route->getRequirement('_entity_type'),
      $route->getRequirement('_bundle')
    ), $entity_type_manager);
    // Only add the unserialized data if there is something there.
    $extra_parameters = $unserialized ? [$unserialized, $request] : [$request];
    try {
      $response = call_user_func_array([$resource, $action], array_merge($parameters, $extra_parameters));
    }
    catch (HttpException $e) {
      $error['error'] = $e->getMessage();
      $content = $serializer->serialize($error, $format);
      // Add the default content type, but only if the headers from the
      // exception have not specified it already.
      $headers = $e->getHeaders() + array('Content-Type' => $request->getMimeType($format));
      return new Response($content, $e->getStatusCode(), $headers);
    }

    return $response instanceof ResourceResponse ?
      $this->renderResponse($request, $response, $serializer, $format) :
      $response;
  }

  /**
   * Renders a resource response.
   *
   * Serialization can invoke rendering (e.g., generating URLs), but the
   * serialization API does not provide a mechanism to collect the
   * bubbleable metadata associated with that (e.g., language and other
   * contexts), so instead, allow those to "leak" and collect them here in
   * a render context.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\rest\ResourceResponse $response
   *   The response from the REST resource.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer to use.
   * @param string $format
   *   The response format.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The altered response.
   *
   * @todo Add test coverage for language negotiation contexts in
   *   https://www.drupal.org/node/2135829.
   */
  protected function renderResponse(Request $request, ResourceResponse $response, SerializerInterface $serializer, $format) {
    $data = $response->getResponseData();
    $context = new RenderContext();
    $output = $this->container->get('renderer')
      ->executeInRenderContext($context, function () use ($serializer, $data, $format, $request) {
        return $serializer->serialize($data, $format, ['request' => $request]);
      });
    $response->setContent($output);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    $response->headers->set('Content-Type', $request->getMimeType($format));
    // Add rest settings config's cache tags.
    $response->addCacheableDependency($this->container->get('config.factory')
      ->get('rest.settings'));

    return $response;
  }

  /**
   * Deserializes the sent data.
   *
   * @todo Add this docblock.
   */
  protected function deserializeBody(Request $request, SerializerInterface $serializer, $serialization_class) {
    $received = $request->getContent();
    $method = strtolower($request->getMethod());
    if (empty($received)) {
      return NULL;
    }
    $format = $request->getContentType();
    try {
      return $serializer->deserialize($received, $serialization_class, $format, array('request_method' => $method));
    }
    catch (UnexpectedValueException $e) {
      $error['error'] = $e->getMessage();
      $content = $serializer->serialize($error, $format);
      return new Response($content, 400, array('Content-Type' => $request->getMimeType($format)));
    }
  }

  /**
   * Gets the method to execute in the entity resource.
   */
  protected function action(RouteMatchInterface $route_match, $method) {
    $on_relationship = $route_match->getParameter('on_relationship');
    $related = (bool) $route_match->getParameter('related');
    if ($related || $on_relationship) {
      throw new \Exception('Not yet implemented');
    }
    return $this->getEntity($route_match) ? 'getIndividual' : 'getCollection';
  }

  /**
   * Gets the entity for the operation.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The matched route.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The upcasted entity.
   */
  protected function getEntity(RouteMatchInterface $route_match) {
    $route = $route_match->getRouteObject();
    return $route_match->getParameter($route->getRequirement('_entity_type'));
  }

}
