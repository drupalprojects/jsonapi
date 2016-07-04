<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\Context\CurrentContextInterface;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Drupal\jsonapi\RelationshipInterface;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts the Drupal content entity object structure to a HAL array structure.
 */
class ContentEntityNormalizer extends EntityNormalizer {

}
