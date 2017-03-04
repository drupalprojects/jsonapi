<?php

namespace Drupal\jsonapi\Context;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Service which resolves public field names to and from Drupal field names.
 *
 * @internal
 */
class FieldResolver {

  /**
   * The entity type id.
   *
   * @var \Drupal\jsonapi\Context\CurrentContext
   */
  protected $currentContext;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Creates a FieldResolver instance.
   *
   * @param \Drupal\jsonapi\Context\CurrentContext $current_context
   *   The JSON API CurrentContext service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   */
  public function __construct(CurrentContext $current_context, EntityFieldManagerInterface $field_manager) {
    $this->currentContext = $current_context;
    $this->fieldManager = $field_manager;
  }

  /**
   * Maps a Drupal field name to a public field name.
   *
   * Example:
   *   'field_author.entity.field_first_name' -> 'author.firstName'.
   *
   * @param string $field_name
   *   The Drupal field name to map to a public field name.
   *
   * @return string
   *   The mapped field name.
   */
  public function resolveExternal($internal_field_name) {
    // Yet to be implemented.
    return $internal_field_name;
  }

  /**
   * Maps a public field name to a Drupal field name.
   *
   * Example:
   *   'author.firstName' -> 'field_author.entity.field_first_name'.
   *
   * @param string $field_name
   *   The public field name to map to a Drupal field name.
   *
   * @return string
   *   The mapped field name.
   */
  public function resolveInternal($external_field_name) {
    if (empty($external_field_name)) {
      throw new BadRequestHttpException('No field name was provided for the filter.');
    }
    // Right now we are exposing all the fields with the name they have in
    // the Drupal backend. But this may change in the future.
    if (strpos($external_field_name, '.') === FALSE) {
      return $external_field_name;
    }
    // Turns 'uid.field_category.name' into
    // 'uid.entity.field_category.entity.name'. This may be too simple, but it
    // works for the time being.
    $parts = explode('.', $external_field_name);
    $entity_type_id = $this->currentContext->getResourceType()->getEntityTypeId();
    $reference_breadcrumbs = [];
    while ($field_name = array_shift($parts)) {
      if (!$definitions = $this->fieldManager->getFieldStorageDefinitions($entity_type_id)) {
        throw new BadRequestHttpException(sprintf('Invalid nested filtering. There is no entity type "%s".', $entity_type_id));
      }
      if (empty($definitions[$field_name])) {
        throw new BadRequestHttpException(sprintf('Invalid nested filtering. Invalid entity reference "%s".', $field_name));
      }
      array_push($reference_breadcrumbs, $field_name);
      // Update the entity type with the referenced type.
      $entity_type_id = $definitions[$field_name]->getSetting('target_type');
      // $field_name may not be a reference field. In that case we should treat
      //the rest of the parts as complex fields.
      if (empty($entity_type_id)) {
        // This is the path from the initial entity type to the entity type that
        // contains $field_name. This path is a set of entity references.
        $entity_path = implode('.entity.', $reference_breadcrumbs);
        // This is the path from the final entity type to the selected field
        //column.
        $field_path = implode('.', $parts);

        return implode('.', array_filter([$entity_path, $field_path]));
      }
    }

    return implode('.entity.', $reference_breadcrumbs);
  }

}
