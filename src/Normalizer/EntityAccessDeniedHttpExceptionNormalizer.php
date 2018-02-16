<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\Normalizer\Value\EntityAccessDeniedHttpExceptionNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Normalizes an EntityAccessDeniedException.
 *
 * Normalizes an EntityAccessDeniedException in compliance with the JSON API
 * specification. A source pointer is added to help client applications report
 * which entity was access denied.
 *
 * @see http://jsonapi.org/format/#error-objects
 *
 * @internal
 */
class EntityAccessDeniedHttpExceptionNormalizer extends HttpExceptionNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityAccessDeniedHttpException::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $errors = $this->buildErrorObjects($object);

    $errors = array_map(function ($error) {
      return new FieldItemNormalizerValue([$error]);
    }, $errors);

    return new EntityAccessDeniedHttpExceptionNormalizerValue(
      $errors,
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      $object->getResourceIdentifier()
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildErrorObjects(HttpException $exception) {
    $errors = parent::buildErrorObjects($exception);

    if ($exception instanceof EntityAccessDeniedHttpException) {
      $error = $exception->getError();
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $pointer = $error['pointer'];
      $reason = $error['reason'];
      $errors[0]['source']['pointer'] = $pointer;

      if ($reason) {
        $errors[0]['detail'] = isset($errors[0]['detail']) ? $errors[0]['detail'] . ' ' . $reason : $reason;
      }
    }

    return $errors;
  }

}
