<?php

namespace Drupal\jsonapi_entity_test\Entity;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Test entity class.
 *
 * @ContentEntityType(
 *   id = "jsonapi_entity_test_no_label",
 *   label = @Translation("Entity Test without label"),
 *   internal = TRUE,
 *   persistent_cache = FALSE,
 *   base_table = "entity_test_no_label",
 *   handlers = {
 *     "access" = "Drupal\entity_test\EntityTestAccessControlHandler",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *   },
 * )
 *
 * @internal
 * @todo remove this class when this lands and JSON API requires a Drupal core version that contains it: https://www.drupal.org/project/drupal/issues/2943209
 */
class EntityTestNoLabel extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getName();
  }

}
