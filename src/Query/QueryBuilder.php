<?php

/**
 * @file Contains \Drupal\jsonapi\Query.
 */

namespace Drupal\jsonapi\Query;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Routing\Param\Filter;
use Drupal\jsonapi\Routing\Param\JsonApiParamInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;

class QueryBuilder implements QueryBuilderInterface {

  /**
   * The entity type object that should be used for the query.
   */
  protected $entityType;

  /**
   * The options to build with which to build a query.
   */
  protected $options = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON API current context service.
   *
   * @var \Drupal\jsonapi\Context\CurrentContextInterface
   */
  protected $currentContext;

  /**
   * Contructs a new QueryBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *  An instance of a QueryFactory.
   * @param \Drupal\jsonapi\Context\CurrentContextInterface $current_context
   *  An instance of the current context service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CurrentContextInterface $current_context) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentContext = $current_context;
  }

  /**
   * {@inheritdoc}
   */
  public function newQuery(EntityTypeInterface $entity_type) {
    $this->entityType = $entity_type;

    $this->configureFromContext();

    $query = $this->entityTypeManager
      ->getStorage($this->entityType->id())
      ->getQuery()
      ->accessCheck(TRUE);

    // This applies each option from the option tree to the query before
    // returning it.
    $applied_query = array_reduce($this->options, function ($query, $option) {
      return $option->apply($query);
    }, $query);

    return $applied_query ? $applied_query : $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function configureFromContext() {
    if ($filter = $this->currentContext->getJsonApiParameter('filter')) {
      $this->configureFilter($filter);
    }
    if ($sort = $this->currentContext->getJsonApiParameter('sort')) {
      $this->configureSort($sort);
    }
  }

  /**
   * Configures the query builder from a Filter parameter.
   *
   * @param \Drupal\jsonapi\Routing\Param\JsonApiParamInterface $param
   *   A Filter parameter from which to configure this query builder.
   */
  protected function configureFilter(JsonApiParamInterface $param) {
    $extracted = [];

    $filter_collector = function ($filter, $filter_index) use (&$extracted) {
      $option_maker = function ($properties, $filter_type) use (&$extracted, $filter_index) {
        switch ($filter_type) {
          case 'condition':
            $extracted[] = $this->newCondtionOption($filter_index, $properties);
            break;

          case 'group':
            $extracted[] = $this->newGroupOption($filter_index, $properties);
            break;

          case 'exists':
            break;
        };
      };

      array_walk($filter, $option_maker);
    };

    $parameter = $param->get();
    array_walk($parameter, $filter_collector);

    $this->buildTree($extracted);
  }

  /**
   * Configures the query builder from a Sort parameter.
   *
   * @param \Drupal\jsonapi\Routing\Param\JsonApiParamInterface $param
   *   A Sort parameter from which to configure this query builder.
   */
  protected function configureSort(JsonApiParamInterface $param) {
    $extracted = [];

    $sort_collector = function ($sort, $sort_index) use (&$extracted) {
      $extracted[] = $this->newSortOption($sort_index, $sort);
    };

    array_walk($param->get(), $sort_collector);

    $this->buildTree($extracted);
  }

  /**
   * Returns a new ConditionOption.
   *
   * @param string $condition_id
   *   A unique id for the option.
   * @param array $properties
   *   The condition properties.
   *
   * @return \Drupal\jsonapi\Query\ConditionOption
   *   The condition object.
   */
  protected function newCondtionOption($condition_id, array $properties) {
    $langcode = isset($properties['langcode']) ? $properties['langcode'] : NULL;
    $group = isset($properties['group']) ? $properties['group'] : NULL;
    return new ConditionOption(
      $condition_id,
      $properties['field'],
      $properties['value'],
      $properties['operator'],
      $langcode,
      $group
    );
  }

  /**
   * Returns a new GroupOption.
   *
   * @param string $id
   *   A unique id for the option.
   * @param array $properties
   *   The group properties.
   *
   * @return \Drupal\jsonapi\Query\GroupOption
   *   The group object.
   */
  protected function newGroupOption($id, array $properties) {
    $parent_group = isset($properties['group']) ? $properties['group'] : NULL;
    return new GroupOption( $id, $properties['conjunction'], $parent_group);
  }

  /**
   * Returns a new SortOption.
   *
   * @param string $id
   *   A unique id for the option.
   * @param array $properties
   *   The sort properties.
   *
   * @return \Drupal\jsonapi\Query\SortOption
   *   The sort object.
   */
  protected function newSortOption($id, array $properties) {
    // TODO: We need to figure out some way to support langcode on these sorts.
    return new SortOption($id, $properties['value'], $properties['direction']);
  }

  /**
   * Returns a new ExistsOption.
   *
   * @param string $id
   *   A unique id for the option.
   * @param array $properties
   *   The condition properties.
   *
   * @return \Drupal\jsonapi\Query\ExistsOption
   *   The condition object.
   */
  protected function newExistsOptions($id, array $properties) {
    $langcode = isset($properties['langcode']) ? $properties['langcode'] : NULL;
    $group = isset($properties['group']) ? $properties['group'] : NULL;
    return new ExistsOption(
      $id,
      $properties['field'],
      $properties['exists'],
      $langcode,
      $group
    );
  }

  /**
   * Builds a tree of QueryOptions.
   *
   * @param \Drupal\jsonapi\Query\QueryOptionInterface[] $options
   *   An array of QueryOptions.
   */
  protected function buildTree(array $options) {
    $remaining = $options;
    while (!empty($remaining)) {
      $insert = array_pop($remaining);
      if (method_exists($insert, 'parentId') && $parent_id = $insert->parentId()) {
        if (!$this->insert($parent_id, $insert)) {
          array_unshift($remaining, $insert);
        }
      }
      else {
        $this->options[$insert->id()] = $insert;
      }
    }
  }

  /**
   * Inserts a QueryOption into the appropriate child QueryOption.
   *
   * @param string $target_id
   *   Unique ID of the intended QueryOption parent.
   * @param \Drupal\jsonapi\Query\QueryOptionInterface $option
   *   The QueryOption to insert.
   *
   * @return bool
   *   Whether the option could be inserted or not.
   */
  protected function insert($target_id, QueryOptionInterface $option) {
    if (!empty($this->options)) {
      $find_target_child = function ($child, $option) use ($target_id) {
        if ($child) {
          return $child;
        }
        if (
          $option->id() == $target_id ||
          (method_exists($option, 'hasChild') && $option->hasChild($target_id))
        ) {
          return $option->id();
        }
        return FALSE;
      };

      if ($appropriate_child = array_reduce($this->options, $find_target_child, NULL)) {
        return $this->options[$appropriate_child]->insert($target_id, $option);
      }
    }

    return FALSE;
  }

}
