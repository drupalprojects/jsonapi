<?php

namespace Drupal\jsonapi\Exception;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @internal
 */
class SerializableHttpException extends HttpException {

  use DependencySerializationTrait;

}
