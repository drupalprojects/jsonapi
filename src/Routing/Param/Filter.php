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

  protected static $multivalue_operators = ['IN', 'NOT IN', 'BETWEEN'];

  /**
   * {@inheritdoc}
   */
  protected function expand() {
    // We should always get an array for the filter.
    if (!is_array($this->original)) {
      throw new BadRequestHttpException('Incorrect value passed to the filter parameter.');
    }
    return array_map(function ($item) {
      $filter = $item;
      if (!is_array($filter) || empty($filter['value'])) {
        // This is the filter[$field_name]=$value scenario.
        $filter = ['value' => [$filter]];
      }
      if (is_string($filter['value'])) {
        // This is the filter[$field_name][value]=$value scenario.
        $filter['value'] = [$filter['value']];
      }
      if (empty($filter['operator'])) {
        // Add the default operator for every value.
        $filter['operator'] = array_map(function () {
          return '=';
        }, $filter['value']);
      }
      if (is_string($filter['operator'])) {
        $filter['operator'] = [$filter['operator']];
      }
      // If there are more values than operators, check the operator type.
      if (count($filter['value']) != count($filter['operator'])) {
        if (in_array($filter['operator'][0], static::$multivalue_operators)) {
          // If this is a multiple value operator, just use the first operator.
          $filter['operator'] = [$filter['operator'][0]];
        }
        else {
          // Pad the operators with '='.
          $filter['operator'] = array_pad($filter['operator'], count($filter['value']), '=');
        }
      }
      $filter['multivalue'] = in_array($filter['operator'][0], static::$multivalue_operators);
      return $filter;
    }, $this->original);
  }

}
