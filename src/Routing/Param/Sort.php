<?php

namespace Drupal\jsonapi\Routing\Param;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * Class Sort.
 *
 * @package Drupal\jsonapi\Routing\Param
 */
class Sort extends JsonApiParamBase {

  /**
   * {@inheritdoc}
   */
  const KEY_NAME = 'sort';

  /**
   * {@inheritdoc}
   */
  protected function expand() {
    if (!is_string($this->original) || empty($this->original)) {
      throw new BadRequestHttpException('You need to provide a string for the sort parameter.');
    }
    return array_map(function ($item) {
      $sort = $item;
      // Expand every coma-separated sort value.
      $sort = ['value' => $sort, 'direction' => 'ASC'];
      // If the value starts with a minus symbol, then it's descending.
      if ($sort['value']{0} == '-') {
        $sort['direction'] = 'DESC';
        $sort['value'] = substr($sort['value'], 1);
      }
      return $sort;
    }, explode(',', $this->original));
  }

}
