<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Encoder\JsonEncoder.
 */

namespace Drupal\jsonapi\Encoder;

use Drupal\jsonapi\Normalizer\Value\ValueExtractorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;

/**
 * Encodes JSON API data.
 *
 * Simply respond to application/vnd.api+json format requests using encoder.
 */
class JsonEncoder extends SymfonyJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected $format = 'api_json';

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    return $format == $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    return $format == $this->format;
  }

  public function encode($data, $format, array $context = []) {
    if ($data instanceof ValueExtractorInterface) {
      $data = $data->rasterizeValue();
    }
    if (!empty($context['data_wrapper'])) {
      $data = [$context['data_wrapper'] => $data];
    }
    return parent::encode($data, $format, $context);
  }


}
