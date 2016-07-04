<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides schema for the various exposed resources.
 */
class SchemaController extends ControllerBase {

  /**
   * Returns schema from a given type data definition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The type data.
   *
   * @return array
   *   The schema.
   */
  protected function buildSchemaFromDataDefinition(DataDefinitionInterface $data_definition) {
    $schema = [];

    if ($label = $data_definition->getLabel()) {
      $schema['title'] = $label;
    }
    if ($description = $data_definition->getDescription()) {
      $schema['description'] = $description;
    }

    if ($data_definition instanceof ListDataDefinitionInterface) {
      $schema['type'] = 'array';
      $schema['items'] = $this->buildSchemaFromDataDefinition($data_definition->getItemDefinition());
    }
    elseif ($data_definition instanceof ComplexDataDefinitionInterface) {
      $schema['type'] = 'object';
      $schema['properties'] = array_filter(array_map(function (DataDefinitionInterface $sub_data_definition) {
        if (!$sub_data_definition->isComputed()) {
          return $this->buildSchemaFromDataDefinition($sub_data_definition);
        }
        return NULL;
      }, $data_definition->getPropertyDefinitions()));

      $schema['required'] = array_keys(array_filter($data_definition->getPropertyDefinitions(), function (DataDefinitionInterface $definition) {
        return $definition->isRequired();
      }));
    }
    else {
      $schema['type'] = $this->convertTypeDataToJsonType($data_definition->getDataType());
    }

    return $schema;
  }

  /**
   * Converts a type data type to JSON.
   *
   * @param string $type
   *   The type data type.
   *
   * @return string
   *   Returns the JSON data type.
   */
  protected function convertTypeDataToJsonType($type) {
    $json_type = '';
    switch ($type) {
      case 'string':
        $json_type = 'string';
        break;
      case 'integer':
      case 'timestamp':
        $json_type = 'integer';
        break;
      case 'boolean':
        $json_type = 'boolean';
        break;
      case 'float':
        $json_type = 'number';
        break;
    }

    return $json_type;
  }

  /**
   * Provides schema for the individual entity resource.
   *
   * @param string $typed_data_id
   *   The type data ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the JSON scheme.
   */
  public function entitySchema($typed_data_id) {
    /** @var \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager */
    $typed_data_manager = \Drupal::service('typed_data_manager');
    $data_definition = $typed_data_manager->createDataDefinition($typed_data_id);

    $schema = [];
    $schema['type'] = 'object';
    $schema['properties'] = [
      'data' => [
        'type' => 'object',
        'properties' => [
          'attributes' => $this->buildSchemaFromDataDefinition($data_definition),
        ],
      ],
    ];

    return new JsonResponse($schema);
  }

  /**
   * Provides schema for the collection entity resource.
   *
   * @param string $typed_data_id
   *   The type data ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the JSON scheme.
   */
  public function entityCollectionSchema($typed_data_id) {
    /** @var \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager */
    $typed_data_manager = \Drupal::service('typed_data_manager');
    $data_definition = $typed_data_manager->createDataDefinition($typed_data_id);

    $schema = [];
    $schema['type'] = 'object';
    $schema['properties'] = [
      'data' => [
        'type' => 'array',
        'items' => [
          'type' => 'object',
          'properties' => [
            'attributes' => $this->buildSchemaFromDataDefinition($data_definition),
          ],
        ],
      ],
    ];

    return new JsonResponse($schema);
  }

}
