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

  /**
   * {@inheritdoc}
   *
   * @see http://jsonapi.org/format/#errors
   */
  public function encode($data, $format, array $context = []) {
    // Make sure that any auto-normalizable object gets normalized before
    // encoding. This is specially important to generate the errors in partial
    // success responses.
    if ($data instanceof ValueExtractorInterface) {
      $data = $data->rasterizeValue();
    }
    // Allows wrapping the encoded output. This is so we can use the same
    // encoder and normalizers when serializing HttpExceptions to match the
    // JSON API specification.
    if (!empty($context['data_wrapper'])) {
      $data = [$context['data_wrapper'] => $data];
    }
    return parent::encode($data, $format, $context);
  }


}
