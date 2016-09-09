<?php

namespace Drupal\jsonapi\Plugin;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;

class FileDownloadUrl extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name, TypedDataInterface $parent) {
    parent::__construct($definition, $name, $parent);
    $this->setValue($this->compute());
  }

  /**
   * Creates a relative URL out of a URI.
   *
   * This is a wrapper to the procedural code for testing purposes. For obvious
   * reasons this method will not be unit tested, but that is fine since it's
   * only using already tested Drupal API functions.
   *
   * @param string $uri
   *   The URI to transform.
   *
   * @return string
   *   The transformed relative URL.
   */
  protected function fileCreateRootRelativeUrl($uri) {
    return file_url_transform_relative(file_create_url($uri));
  }

  /**
   * Fetch the list of URIs from the current entity.
   *
   * This is a wrapper to avoid mocking the whole chain in testing. For these
   * reason this method will not be unit tested, but that is fine since it's
   * only using already tested Drupal API functions.
   *
   * @return array
   *   The array of values.
   */
  protected function getUris() {
    return $this->getEntity()->get('uri')->getValue();
  }

  /**
   * Computes the file download url field.
   *
   * @return array
   *   The array of values to use for the computed field.
   */
  public function compute() {
    $uri_values = $this->getUris();
    return array_map(function ($uri_value) {
      return ['value' => $this->fileCreateRootRelativeUrl($uri_value['value'])];
    }, $uri_values);
  }

}
