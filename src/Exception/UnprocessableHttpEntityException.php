<?php

namespace Drupal\jsonapi\Exception;

use Drupal\Core\Entity\EntityConstraintViolationListInterface;

/**
 * @internal
 */
class UnprocessableHttpEntityException extends SerializableHttpException {

  /**
   * The constraint violations associated with this exception.
   *
   * @var \Drupal\Core\Entity\EntityConstraintViolationListInterface
   */
  protected $violations;

  /**
   * UnprocessableHttpEntityException constructor.
   *
   * @param array $violations
   * @param \Exception|null $previous
   * @param array $headers
   * @param int $code
   */
  public function __construct(\Exception $previous = NULL, array $headers = array(), $code = 0) {
    parent::__construct(422, "Unprocessable Entity: validation failed.", $previous, $headers, $code);
  }

  /**
   * Gets the constraint violations associated with this exception.
   *
   * @return \Drupal\Core\Entity\EntityConstraintViolationListInterface
   */
  public function getViolations() {
    return $this->violations;
  }

  /**
   * Sets the constraint violations associated with this exception.
   *
   * @param \Drupal\Core\Entity\EntityConstraintViolationListInterface $violations
   *   The constraint violations.
   */
  public function setViolations(EntityConstraintViolationListInterface $violations) {
    $this->violations = $violations;
  }

}
