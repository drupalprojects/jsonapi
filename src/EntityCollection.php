<?php

namespace Drupal\jsonapi;

/**
 * Class EntityCollection.
 *
 * @package Drupal\jsonapi
 */
class EntityCollection implements \IteratorAggregate, \Countable {

  /**
   * Entity storage.
   *
   * @var array
   */
  protected $entities;

  /**
   * Instantiates a EntityCollection object.
   *
   * @param array $entities
   *   The entities for the collection.
   */
  public function __construct(array $entities) {
    $this->entities = array_values($entities);
  }

  /**
   * Returns an iterator for entities.
   *
   * @return \ArrayIterator
   *   An \ArrayIterator instance
   */
  public function getIterator() {
    return new \ArrayIterator($this->entities);
  }

  /**
   * Returns the number of entities.
   *
   * @return int
   *   The number of parameters
   */
  public function count() {
    return count($this->entities);
  }

}
