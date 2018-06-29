<?php

namespace Drupal\jsonapi\EventSubscriber;

use Drupal\jsonapi\JsonApiSpec;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Request subscriber that validates a JSON API request.
 *
 * @internal
 */
class JsonApiRequestValidator implements EventSubscriberInterface {

  /**
   * Validates JSON API requests.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event to process.
   */
  public function onRequest(GetResponseEvent $event) {
    $request = $event->getRequest();
    if ($request->getRequestFormat() !== 'api_json') {
      return;
    }

    $this->validateQueryParams($request);
  }

  /**
   * Validates custom (implementation-specific) query parameter names.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request for which to validate JSON API query parameters.
   *
   * @return \Drupal\jsonapi\ResourceResponse|null
   *   A JSON API resource response.
   *
   * @see http://jsonapi.org/format/#query-parameters
   */
  protected function validateQueryParams(Request $request) {
    $invalid_query_params = [];
    foreach (array_keys($request->query->all()) as $query_parameter_name) {
      // Ignore reserved (official) query parameters.
      if (in_array($query_parameter_name, JsonApiSpec::getReservedQueryParameters())) {
        continue;
      }

      if (!JsonApiSpec::isValidCustomQueryParameter($query_parameter_name)) {
        $invalid_query_params[] = $query_parameter_name;
      }
    }

    // @todo remove this line and/or comment in https://www.drupal.org/project/jsonapi/issues/2977600.
    // Drupal uses the `_format` query parameter for Content-Type negotiation.
    $invalid_query_params = array_diff($invalid_query_params, ['_format']);

    if (empty($invalid_query_params)) {
      return NULL;
    }

    $message = sprintf('The following query parameters violate the JSON API spec: \'%s\'.', implode("', '", $invalid_query_params));
    $exception = new BadRequestHttpException($message);
    $exception->setHeaders(['Link' => 'http://jsonapi.org/format/#query-parameters']);
    throw $exception;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }

}
