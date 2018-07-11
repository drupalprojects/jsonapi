<?php

namespace Drupal\jsonapi\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Defines a class to check access to related and relationship routes.
 *
 * @internal
 */
class RelationshipFieldAccess implements AccessInterface {

  /**
   * The route requirement key for this access check.
   *
   * @var string
   */
  const ROUTE_REQUIREMENT_KEY = '_jsonapi_relationship_field_access';

  /**
   * Checks access to the relationship field on the given route.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request object.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request, Route $route, AccountInterface $account) {
    $relationship_field_name = $route->getRequirement(static::ROUTE_REQUIREMENT_KEY);
    $field_operation = $request->isMethodCacheable() ? 'view' : 'edit';
    $entity_operation = $request->isMethodCacheable() ? 'view' : 'update';
    if ($resource_type = $request->get(Routes::RESOURCE_TYPE_KEY)) {
      $entity = $request->get($resource_type->getEntityTypeId());
      if ($entity instanceof FieldableEntityInterface && $entity->hasField($relationship_field_name)) {
        $entity_access = $entity->access($entity_operation, $account, TRUE);
        $field_access = $entity->get($relationship_field_name)->access($field_operation, $account, TRUE);
        $access_result = $entity_access->andIf($field_access);
        if (!$access_result->isAllowed()) {
          $reason = "The current user is not allowed to {$field_operation} this relationship.";
          $access_reason = $access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : NULL;
          return empty($access_reason)
            ? $access_result->isForbidden() ? AccessResult::forbidden($reason) : AccessResult::neutral($reason)
            : $access_result->setReason($reason . " {$access_reason}");
        }
        return $access_result;
      }
    }
    return AccessResult::neutral();
  }

}
