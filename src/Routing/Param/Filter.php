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

    $expanded = array_map(function ($filter_item) {
      if (isset($filter_item['field']) && isset($filter_item['value'])) {
        $expanded_filter = [
          'condition' => $filter_item,
        ];

        if (!isset($expanded_filter['operator'])) {
          $expanded_filter['condition']['operator'] = '=';
        }

        return $expanded_filter;
      }
      else {
        return $filter_item;
      }
    }, $this->original);

    return $expanded;
  }

}
