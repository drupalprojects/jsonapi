<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Context\CurrentContext;
use Drupal\jsonapi\Error\ErrorHandler;
use Drupal\jsonapi\Exception\SerializableHttpException;
use Drupal\jsonapi\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Acts as intermediate request forwarder for resource plugins.
 *
 * @internal
 */
class RequestHandler implements ContainerAwareInterface, ContainerInjectionInterface {

  use ContainerAwareTrait;

  protected static $requiredCacheContexts = ['user.permissions'];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * Handles a web API request.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response object.
   */
  public function handle(RouteMatchInterface $route_match, Request $request) {
    $method = strtolower($request->getMethod());
    $route = $route_match->getRouteObject();

    // Deserialize incoming data if available.
    /* @var \Symfony\Component\Serializer\SerializerInterface $serializer */
    $serializer = $this->container->get('serializer');
    /* @var \Drupal\jsonapi\Context\CurrentContext $current_context */
    $current_context = $this->container->get('jsonapi.current_context');
    $unserialized = $this->deserializeBody($request, $serializer, $route->getOption('serialization_class'), $current_context);
    $format = $request->getRequestFormat();
    if ($unserialized instanceof Response && !$unserialized->isSuccessful()) {
      return $unserialized;
    }

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
    $action = $this->action($route_match, $method);
    $resource = $this->resourceFactory($route, $current_context);

    // Only add the unserialized data if there is something there.
    $extra_parameters = $unserialized ? [$unserialized, $request] : [$request];

    /** @var \Drupal\jsonapi\Error\ErrorHandler $error_handler */
    $error_handler = $this->container->get('jsonapi.error_handler');
    $error_handler->register();
    // Execute the request in context so the cacheable metadata from the entity
    // grants system is caught and added to the response. This is surfaced when
    // executing the underlying entity query.
    $context = new RenderContext();
    /** @var \Drupal\Core\Cache\CacheableResponseInterface $response */
    $response = $this->container->get('renderer')
      ->executeInRenderContext($context, function () use ($resource, $action, $parameters, $extra_parameters) {
        return call_user_func_array([$resource, $action], array_merge($parameters, $extra_parameters));
      });
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }
    $error_handler->restore();

    return $this->renderJsonApiResponse($request, $response, $serializer, $format, $error_handler);
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
   * @param \Drupal\Core\Cache\CacheableResponseInterface $response
   *   The response from the REST resource.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer to use.
   * @param string $format
   *   The response format.
   * @param \Drupal\jsonapi\Error\ErrorHandler $error_handler
   *   The error handler service.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The altered response.
   */
  protected function renderJsonApiResponse(Request $request, ResourceResponse $response, SerializerInterface $serializer, $format, ErrorHandler $error_handler) {
    $data = $response->getResponseData();
    $context = new RenderContext();

    $cacheable_metadata = $response->getCacheableMetadata();
    // Make sure to include the default cacheable metadata, since it won't be
    // added if you don't user render arrays and the HtmlRenderer. We are not
    // using the container variable '%renderer.config%' because is too tied to
    // HTML generation.
    $cacheable_metadata->addCacheContexts(static::$requiredCacheContexts);

    // Make sure that any PHP error is surfaced as a serializable exception.
    $error_handler->register();
    $output = $this->container->get('renderer')
      ->executeInRenderContext($context, function () use (
        $serializer,
        $data,
        $format,
        $request,
        $cacheable_metadata
      ) {
        // The serializer receives the response's cacheability metadata object
        // as serialization context. Normalizers called by the serializer then
        // refine this cacheability metadata, and thus they are effectively
        // updating the response object's cacheability.
        return $serializer->serialize($data, $format, ['request' => $request, 'cacheable_metadata' => $cacheable_metadata]);
      });
    $error_handler->restore();
    $response->setContent($output);
    if (!$context->isEmpty()) {
      $response->addCacheableDependency($context->pop());
    }

    $response->headers->set('Content-Type', $request->getMimeType($format));
    // Add rest settings config's cache tags.
    $response->addCacheableDependency($this->container->get('config.factory')
      ->get('jsonapi.resource_info'));

    assert('$this->validateResponse($response)', 'A JSON API response failed validation (see the logs for details). Please report this in the issue queue on drupal.org');

    return $response;
  }

  /**
   * Deserializes the sent data.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer for the deserialization of the input data.
   * @param string $serialization_class
   *   The class the input data needs to deserialize into.
   * @param \Drupal\jsonapi\Context\CurrentContext $current_context
   *   The current context
   *
   * @return mixed
   *   The deserialized data or a Response object in case of error.
   */
  public function deserializeBody(Request $request, SerializerInterface $serializer, $serialization_class, CurrentContext $current_context) {
    $received = $request->getContent();
    if (empty($received)) {
      return NULL;
    }
    $format = $request->getContentType();
    try {
      return $serializer->deserialize($received, $serialization_class, $format, [
        'related' => $request->get('related'),
        'target_entity' => $request->get($current_context->getResourceType()->getEntityTypeId()),
        'resource_type' => $current_context->getResourceType(),
      ]);
    }
    catch (UnexpectedValueException $e) {
      throw new SerializableHttpException(
        422,
        sprintf('There was an error un-serializing the data. Message: %s.', $e->getMessage()),
        $e
      );
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
   * @param \Drupal\jsonapi\Context\CurrentContext $current_context
   *   The current context.
   *
   * @return \Drupal\jsonapi\Controller\EntityResource
   *   The instantiated resource.
   */
  protected function resourceFactory(Route $route, CurrentContext $current_context) {
    /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository */
    $resource_type_repository = $this->container->get('jsonapi.resource_type.repository');
    /* @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /* @var \Drupal\jsonapi\Query\QueryBuilder $query_builder */
    $query_builder = $this->container->get('jsonapi.query_builder');
    /* @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = $this->container->get('entity_field.manager');
    /* @var \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager */
    $plugin_manager = $this->container->get('plugin.manager.field.field_type');
    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = $this->container->get('entity.repository');
    $resource = new EntityResource(
      $resource_type_repository->get($route->getRequirement('_entity_type'), $route->getRequirement('_bundle')),
      $entity_type_manager,
      $query_builder,
      $field_manager,
      $current_context,
      $plugin_manager,
      $entity_repository
    );
    return $resource;
  }

  /**
   * Validates a response against the JSON API specification.
   *
   * @param \Drupal\jsonapi\ResourceResponse $response
   *   The response to validate.
   *
   * @return bool
   *   FALSE if the response failed validation, otherwise TRUE.
   */
  protected static function validateResponse(ResourceResponse $response) {
    if (!class_exists("\\JsonSchema\\Validator")) {
      return TRUE;
    }
    // Do not use Json::decode here since it coerces the response into an
    // associative array, which creates validation errors.
    $response_data = json_decode($response->getContent());
    if (empty($response_data)) {
      return TRUE;
    }

    $validator = new \JsonSchema\Validator;
    $schema_path = DRUPAL_ROOT . '/' . drupal_get_path('module', 'jsonapi') . '/schema.json';

    $validator->check($response_data, (object)['$ref' => 'file://' . $schema_path]);

    if (!$validator->isValid()) {
      \Drupal::logger('jsonapi')->debug('Response failed validation: @data', [
        '@data' => Json::encode($response_data),
      ]);
      \Drupal::logger('jsonapi')->debug('Validation errors: @errors', [
        '@errors' => Json::encode($validator->getErrors()),
      ]);
    }

    return $validator->isValid();
  }

}
