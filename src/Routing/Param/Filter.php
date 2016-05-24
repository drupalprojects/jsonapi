<?php

namespace Drupal\jsonapi\Routing\Param;

use Drupal\Core\Entity\EntityFieldManagerInterface;
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
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Instantiates a Filter object.
   *
   * @param string|\string[] $original
   *   The original data.
   * @param string $entity_type_id
   *   The entity type id of the resource this filter applies to.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   */
  public function __construct($original, $entity_type_id, EntityFieldManagerInterface $field_manager) {
    parent::__construct($original);
    $this->entityTypeId = $entity_type_id;
    $this->fieldManager = $field_manager;
  }

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
    $filter_item['condition']['field'] = $this->queryFieldName($filter_item['condition']['field']);

    return $filter_item;
  }


  /**
   * Gets the field name in the backed usable with the entity condition.
   *
   * @param string $public_name
   *   The field name as exposed in the API.
   *
   * @return string
   *   The field name usable in \Drupal\Core\Entity\Query\QueryInterface.
   */
  protected function queryFieldName($public_name) {
    // Right now we are exposing all the fields with the name they have in
    // the Drupal backend. But this may change in the future.
    if (strpos($public_name, '.') === FALSE) {
      return $public_name;
    }
    // Turns 'uid.field_category.name' into
    // 'uid.entity.field_category.entity.name'. This may be too simple, but it
    // works for the time being.
    $parts = explode('.', $public_name);
    // The last part of the chain is the referenced field, not a relationship.
    $leave_field = array_pop($parts);
    // Prepare the exception only once.
    $exception = new BadRequestHttpException('Invalid nested filtering.');
    $entity_type_id = $this->entityTypeId;
    foreach ($parts as $field_name) {
      if (!$definitions = $this->fieldManager->getFieldStorageDefinitions($entity_type_id)) {
        throw $exception;
      }
      if (
        empty($definitions[$field_name]) ||
        $definitions[$field_name]->getType() != 'entity_reference'
      ) {
        throw $exception;
      }
      // Update the entity type with the referenced type.
      $entity_type_id = $definitions[$field_name]->getSetting('target_type');
    };
    // Put the leave field back before imploding.
    array_push($parts, $leave_field);
    return implode('.entity.', $parts);
  }

}
