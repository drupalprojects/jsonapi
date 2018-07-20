<?php

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Acts as request forwarder for \Drupal\jsonapi\Controller\EntityResource.
 *
 * @internal
 */
class RequestHandler {

  /**
   * The JSON API serializer.
   *
   * @var \Drupal\jsonapi\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The JSON API entity resource controller.
   *
   * @var \Drupal\jsonapi\Controller\EntityResource
   */
  protected $entityResource;

  /**
   * Creates a new RequestHandler instance.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The JSON API serializer.
   */
  public function __construct(SerializerInterface $serializer, EntityResource $entity_resource) {
    $this->serializer = $serializer;
    $this->entityResource = $entity_resource;
  }

  /**
   * Handles a JSON API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return \Drupal\Core\Cache\CacheableResponseInterface
   *   The response object.
   */
  public function handle(Request $request, ResourceType $resource_type) {
    $unserialized = $this->deserialize($request, $resource_type);

    // Determine the request parameters that should be passed to the resource
    // plugin.
    $parameters = ['resource_type' => $resource_type];

    $entity_type_id = $resource_type->getEntityTypeId();
    if ($entity = $request->get($entity_type_id)) {
      $parameters[$entity_type_id] = $entity;
    }

    if ($related = $request->get('related')) {
      $parameters['related'] = $related;
    }

    // Invoke the operation on the resource plugin.
    $action = $this->action($request, $resource_type);

    // Only add the unserialized data if there is something there.
    $extra_parameters = $unserialized ? [$unserialized, $request] : [$request];

    return call_user_func_array([$this->entityResource, $action], array_merge($parameters, $extra_parameters));
  }

  /**
   * Deserializes request body, if any.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return array|null
   *   An object normalization, if there is a valid request body. NULL if there
   *   is no request body.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown if the request body cannot be decoded, or when no request body was
   *   provided with a POST or PATCH request.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown if the request body cannot be denormalized.
   */
  protected function deserialize(Request $request, ResourceType $resource_type) {
    if ($request->isMethodSafe(FALSE)) {
      return NULL;
    }

    // Deserialize incoming data if available.
    $received = $request->getContent();
    $unserialized = NULL;
    if (!empty($received)) {
      // First decode the request data. We can then determine if the
      // serialized data was malformed.
      try {
        $unserialized = $this->serializer->decode($received, 'api_json');
      }
      catch (UnexpectedValueException $e) {
        // If an exception was thrown at this stage, there was a problem
        // decoding the data. Throw a 400 http exception.
        throw new BadRequestHttpException($e->getMessage());
      }

      $field_related = $resource_type->getInternalName($request->get('related'));
      try {
        $unserialized = $this->serializer->denormalize($unserialized, $request->get('serialization_class'), 'api_json', [
          'related' => $field_related,
          'target_entity' => $request->get($resource_type->getEntityTypeId()),
          'resource_type' => $resource_type,
        ]);
      }
      // These two serialization exception types mean there was a problem with
      // the structure of the decoded data and it's not valid.
      catch (UnexpectedValueException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }
      catch (InvalidArgumentException $e) {
        throw new UnprocessableEntityHttpException($e->getMessage());
      }
    }
    elseif ($request->isMethod('POST') || $request->isMethod('PATCH')) {
      throw new BadRequestHttpException('Empty request body.');
    }

    return $unserialized;
  }

  /**
   * Gets the method to execute in the entity resource.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being handled.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type for the current request.
   *
   * @return string
   *   The method to execute in the EntityResource.
   */
  protected function action(Request $request, ResourceType $resource_type) {
    $on_relationship = (bool) $request->get('_on_relationship');
    switch (strtolower($request->getMethod())) {
      case 'head':
      case 'get':
        if ($on_relationship) {
          return 'getRelationship';
        }
        elseif ($request->get('related')) {
          return 'getRelated';
        }
        return $request->get($resource_type->getEntityTypeId()) ? 'getIndividual' : 'getCollection';

      case 'post':
        return ($on_relationship) ? 'createRelationship' : 'createIndividual';

      case 'patch':
        return ($on_relationship) ? 'patchRelationship' : 'patchIndividual';

      case 'delete':
        return ($on_relationship) ? 'deleteRelationship' : 'deleteIndividual';
    }
  }

}
