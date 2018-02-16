<?php

namespace Drupal\jsonapi\Normalizer\Value;

/**
 * Helps normalize exceptions in compliance with the JSON API spec.
 *
 * @internal
 */
class EntityAccessDeniedHttpExceptionNormalizerValue extends FieldNormalizerValue {

  /**
   * The resource identifier of the entity to which access was denied.
   *
   * @var array
   */
  protected $resourceIdentifier;

  /**
   * Instantiate a EntityAccessDeniedHttpExceptionsNormalizerValue object.
   *
   * @param \Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue[] $values
   *   The normalized result.
   * @param int $cardinality
   *   The cardinality of the field list.
   * @param array|null $resource_identifier
   *   The resource identifier for the entity to which access was denied, NULL
   *   when the resource cannot be identified.
   */
  public function __construct(array $values, $cardinality, array $resource_identifier = NULL) {
    parent::__construct($values, $cardinality);
    $this->resourceIdentifier = $resource_identifier;
  }

  /**
   * Rasterize the resource identifier.
   *
   * @return array|null
   *   A rasterized resource identifier or NULL when the resource cannot be
   *   identified.
   */
  public function rasterizeResourceIdentifier() {
    return $this->resourceIdentifier;
  }

}
