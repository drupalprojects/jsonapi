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
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The new query.
   */
  public function newQuery(EntityTypeInterface $entity_type);

}
