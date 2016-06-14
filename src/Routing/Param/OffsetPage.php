<?php

namespace Drupal\jsonapi\Routing\Param;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * Class Page.
 *
 * @package Drupal\jsonapi\Routing\Param
 */
class OffsetPage extends JsonApiParamBase {

  /**
   * {@inheritdoc}
   */
  const KEY_NAME = 'page';

  /**
   * Max size.
   *
   * @var int
   */
  protected $maxSize = 50;

  /**
   * Instantiates an OffsetPage object.
   *
   * @param string|\string[] $original
   *   The original user generated data.
   * @param int $max_size
   *   The maximum size for the pager.
   */
  public function __construct($original, $max_size = NULL) {
    parent::__construct($original);
    if ($max_size) {
      $this->maxSize = $max_size;
    }
  }


  /**
   * {@inheritdoc}
   */
  protected function expand() {
    if (!is_array($this->original)) {
      throw new BadRequestHttpException('The page parameter needs to be an array.');
    }
    $output = $this->original + ['size' => $this->maxSize];
    $output['size'] = $output['size'] > $this->maxSize ?
      $this->maxSize :
      $output['size'];
    return $output;
  }

}
