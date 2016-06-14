<?php

namespace Drupal\jsonapi\Query;


/**
 * Class PagerOption.
 *
 * @package Drupal\jsonapi\Query
 */
class OffsetPagerOption implements QueryOptionInterface {

  /**
   * The size.
   *
   * @var int
   */
  protected $size;

  /**
   * The offset.
   *
   * @var int
   */
  protected $offset;

  /**
   * Creates a PagerOption object.
   *
   * @param int $size
   *   The maximum number of items to return.
   * @param int $offset
   *   The starting element.
   */
  public function __construct($size, $offset = 0) {
    $this->size = $size;
    $this->offset = $offset;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return 'offset_pager';
  }

  /**
   * {@inheritdoc}
   */
  public function apply($query) {
    $query->range($this->offset, $this->size);
  }


}
