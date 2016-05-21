<?php

namespace Drupal\jsonapi\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\jsonapi\Routing\Param\JsonApiParamInterface;

interface QueryBuilderInterface {

  /**
   * Creates a new Entity Query.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type for which to create a query.
   */
  public function newQuery(EntityTypeInterface $entity_type);

  /**
   * Configures a new parameter on the query builder.
   *
   * @param \Drupal\jsonapi\Routing\Param\JsonApiParamInterface $param
   *   The entity type for which to create a query.
   */
  public function configureFromParameter(JsonApiParamInterface $param);

}
