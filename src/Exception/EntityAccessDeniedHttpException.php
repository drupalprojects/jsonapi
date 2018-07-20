<?php

namespace Drupal\jsonapi\Exception;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;

/**
 * Enhances the access denied exception with information about the entity.
 *
 * @internal
 */
class EntityAccessDeniedHttpException extends CacheableAccessDeniedHttpException {

  use DependencySerializationTrait;

  /**
   * The error which caused the 403.
   *
   * The error contains:
   *   - entity: The entity which the current user doens't have access to.
   *   - pointer: A path in the JSON API response structure pointing to the
   *     entity.
   *   - reason: (Optional) An optional reason for this failure.
   *
   * @var array
   */
  protected $error = [];

  /**
   * EntityAccessDeniedHttpException constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity, or NULL when an entity is being created.
   * @param \Drupal\Core\Access\AccessResultInterface $entity_access
   *   The access result.
   * @param string $pointer
   *   (optional) The pointer.
   * @param string $message
   *   (Optional) The display to display.
   * @param \Exception|null $previous
   *   The previous exception.
   * @param int $code
   *   The code.
   */
  public function __construct($entity, AccessResultInterface $entity_access, $pointer, $message = 'The current user is not allowed to GET the selected resource.', \Exception $previous = NULL, $code = 0) {
    assert(is_null($entity) || $entity instanceof EntityInterface);
    parent::__construct(CacheableMetadata::createFromObject($entity_access), $message, $previous, $code);
    $error = [
      'entity' => $entity,
      'pointer' => $pointer,
      'reason' => NULL,
    ];
    if ($entity_access instanceof AccessResultReasonInterface) {
      $error['reason'] = $entity_access->getReason();
    }
    $this->error = $error;
  }

  /**
   * Returns the error.
   *
   * @return array
   *   The error.
   */
  public function getError() {
    return $this->error;
  }

}
