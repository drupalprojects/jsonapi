<?php

namespace Drupal\jsonapi;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;
use Drupal\jsonapi\Error\ErrorHandlerInterface;
use Drupal\jsonapi\Resource\EntityResource;
use Drupal\rest\RequestHandler as RestRequestHandler;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Route;
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
    /* @var \Drupal\jsonapi\Context\CurrentContextInterface $current_context */
    $current_context = $this->container->get('jsonapi.current_context');
    $unserialized = $this->deserializeBody($request, $serializer, $route->getOption('serialization_class'), $current_context);

    // Determine the request parameters that should be passed to the resource
    // plugin.
    $route_parameters = $route_match->getParameters();
    $parameters = array();

    if (!is_null($unserialized)) {
      array_push($unserialized, $parameters);
    }

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
    $resource = $this->resourceFactory($route, $current_context);

    // Only add the unserialized data if there is something there.
    $extra_parameters = $unserialized ? [$unserialized, $request] : [$request];

    /** @var \Drupal\jsonapi\Error\ErrorHandlerInterface $error_handler */
    $error_handler = $this->container->get('jsonapi.error_handler');
    $error_handler->register();
    try {
      $response = call_user_func_array([$resource, $action], array_merge($parameters, $extra_parameters));
    }
    catch (\Exception $error_exception) {
      if (!$error_exception instanceof HttpExceptionInterface) {
        // If this is a generic exception, generate a Server Exception HTTP
        // exception and process it.
        $error_exception = new HttpException(500, $error_exception->getMessage(), $error_exception);
      }
      $content = $serializer->serialize($error_exception, $format);
      // Add the default content type, but only if the headers from the
      // exception have not specified it already.
      $headers = $error_exception->getHeaders() + array('Content-Type' => $request->getMimeType($format));
      $response = new Response($content, $error_exception->getStatusCode(), $headers);
    }
    $error_handler->restore();

    return $response instanceof ResourceResponse ?
      $this->renderJsonApiResponse($request, $response, $serializer, $format, $error_handler) :
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
   * @param \Drupal\rest\ResourceResponseInterface $response
   *   The response from the REST resource.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer to use.
   * @param string $format
   *   The response format.
   * @param \Drupal\jsonapi\Error\ErrorHandlerInterface $error_handler
   *   The error handler service.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The altered response.
   *
   * @todo Add test coverage for language negotiation contexts in
   *   https://www.drupal.org/node/2135829.
   */
  protected function renderJsonApiResponse(Request $request, ResourceResponseInterface $response, SerializerInterface $serializer, $format, ErrorHandlerInterface $error_handler) {
    $data = $response->getResponseData();
    $context = new RenderContext();
    $cacheable_metadata = $response->getCacheableMetadata();
    $output = $this->container->get('renderer')
      ->executeInRenderContext($context, function () use ($serializer, $data, $format, $request, $cacheable_metadata, $error_handler, $response) {
        $error_handler->register();
        try {
          $content = $serializer->serialize($data, $format, ['request' => $request, 'cacheable_metadata' => $cacheable_metadata]);
        }
        catch (\Exception $error_exception) {
          if (!$error_exception instanceof HttpExceptionInterface) {
            // If this is a generic exception, generate a Server Exception HTTP
            // exception and process it.
            $error_exception = new HttpException(500, $error_exception->getMessage(), $error_exception);
          }
          $content = $serializer->serialize($error_exception, $format);
          $response->setStatusCode($error_exception->getStatusCode());
        }
        $error_handler->restore();
        return $content;
      });
    $response->setContent($output);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    $response->headers->set('Content-Type', $request->getMimeType($format));
    // Add rest settings config's cache tags.
    $response->addCacheableDependency($this->container->get('config.factory')
      ->get('jsonapi.resource_info'));

    return $response;
  }

  /**
   * Deserializes the sent data.
   *
   * @todo Add this docblock.
   */
  protected function deserializeBody(Request $request, SerializerInterface $serializer, $serialization_class, CurrentContextInterface $current_context) {
    $received = $request->getContent();
    $method = strtolower($request->getMethod());
    if (empty($received)) {
      return NULL;
    }
    $format = $request->getContentType();
    try {
      return $serializer->deserialize($received, $serialization_class, $format, [
        'request_method' => $method,
        'related' => $request->get('related'),
        'target_entity' => $request->get($current_context->getResourceConfig()->getEntityTypeId()),
        'resource_config' => $current_context->getResourceConfig(),
      ]);
    }
    catch (UnexpectedValueException $e) {
      $error['error'] = $e->getMessage();
      $content = $serializer->serialize($error, $format);
      return new Response($content, 400, array('Content-Type' => $request->getMimeType($format)));
    }
  }

  /**
   * Gets the method to execute in the entity resource.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string $method
   *   The lowercase HTTP method.
   *
   * @return string
   *   The method to execute in the EntityResource.
   */
  protected function action(RouteMatchInterface $route_match, $method) {
    $on_relationship = ($route_match->getRouteObject()->getDefault('_on_relationship'));
    switch ($method) {
      case 'get':
        if ($on_relationship) {
          return 'getRelationship';
        }
        elseif ($route_match->getParameter('related')) {
          return 'getRelated';
        }
        return $this->getEntity($route_match) ? 'getIndividual' : 'getCollection';

      case 'post':
        return ($on_relationship) ? 'createRelationship' : 'createIndividual';

      case 'patch':
        return ($on_relationship) ? 'patchRelationship' : 'patchIndividual';

      case 'delete':
        return ($on_relationship) ? 'deleteRelationship' : 'deleteIndividual';
    }
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

  /**
   * Get the resource.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The matched route.
   * @param \Drupal\jsonapi\Context\CurrentContextInterface $current_context
   *   The current context.
   *
   * @return \Drupal\jsonapi\Resource\EntityResourceInterface
   *   The instantiated resource.
   */
  protected function resourceFactory(Route $route, CurrentContextInterface $current_context) {
    /** @var \Drupal\jsonapi\Configuration\ResourceManagerInterface $resource_manager */
    $resource_manager = $this->container->get('jsonapi.resource.manager');
    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /* @var \Drupal\jsonapi\Query\QueryBuilderInterface $query_builder */
    $query_builder = $this->container->get('jsonapi.query_builder');
    /* @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = $this->container->get('entity_field.manager');
    /* @var \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager */
    $plugin_manager = $this->container->get('plugin.manager.field.field_type');
    $resource = new EntityResource($resource_manager->get($route->getRequirement('_entity_type'), $route->getRequirement('_bundle')), $entity_type_manager, $query_builder, $field_manager, $current_context, $plugin_manager);
    return $resource;
  }

}
