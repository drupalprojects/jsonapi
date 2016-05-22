<?php

namespace Drupal\jsonapi\Routing\Param;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * Class Filter.
 *
 * @package Drupal\jsonapi\Routing\Param
 */
class Filter extends JsonApiParamBase {

  /**
   * {@inheritdoc}
   */
  const KEY_NAME = 'filter';

  /**
   * {@inheritdoc}
   */
  protected function expand() {
    // We should always get an array for the filter.
    if (!is_array($this->original)) {
      throw new BadRequestHttpException('Incorrect value passed to the filter parameter.');
    }

    $expanded = [];
    foreach ($this->original as $filter_index => $filter_item) {
      $expanded[$filter_index] = $this->expandItem($filter_index, $filter_item);
    }
    return $expanded;
  }

  /**
   * Expands a filter item in case a shortcut was used.
   *
   * Possible cases for the conditions:
   *   1. filter[uuid][value]=1234.
   *   2. filter[0][condition][field]=uuid&filter[0][condition][value]=1234.
   *   3. filter[uuid][condition][value]=1234.
   *   4. filter[uuid][value]=1234&filter[uuid][group]=my_group.
   *
   * @param string $filter_index
   *   The index.
   * @param array $filter_item
   *   The raw filter item.
   *
   * @return array
   *   The expanded filter item.
   */
  protected function expandItem($filter_index, array $filter_item) {
    if (isset($filter_item['value'])) {
      if (!isset($filter_item['field'])) {
        $filter_item['field'] = $filter_index;
      }
      $filter_item = [
        'condition' => $filter_item,
      ];

      if (!isset($filter_item['operator'])) {
        $filter_item['condition']['operator'] = '=';
      }
    }

    return $filter_item;
  }

}
