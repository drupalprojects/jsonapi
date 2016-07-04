<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Class EntityReferenceItemNormalizerValueInterface.
 *
 * @package Drupal\jsonapi\Normalizer\Value
 */
interface RelationshipItemNormalizerValueInterface extends FieldItemNormalizerValueInterface {

  /**
   * Sets the resource.
   *
   * @param string $resource
   *   The resource to set.
   */
  public function setResource($resource);

}
