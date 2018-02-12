<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\block\Entity\Block;
use Drupal\Core\Url;

/**
 * JSON API integration test for the "Block" config entity type.
 *
 * @group jsonapi
 */
class BlockTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'block';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'block--block';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->entity->setVisibilityConfig('user_role', [])->save();
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $block = Block::create([
      'plugin' => 'llama_block',
      'region' => 'header',
      'id' => 'llama',
      'theme' => 'classy',
    ]);
    // All blocks can be viewed by the anonymous user by default. An interesting
    // side effect of this is that any anonymous user is also able to read the
    // corresponding block config entity via REST, even if an authentication
    // provider is configured for the block config entity REST resource! In
    // other words: Block entities do not distinguish between 'view' as in
    // "render on a page" and 'view' as in "read the configuration".
    // This prevents that.
    // @todo Fix this in https://www.drupal.org/node/2820315.
    $block->setVisibilityConfig('user_role', [
      'id' => 'user_role',
      'roles' => ['non-existing-role' => 'non-existing-role'],
      'negate' => FALSE,
      'context_mapping' => [
        'user' => '@user.current_user_context:current_user',
      ],
    ]);
    $block->save();

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $self_url = Url::fromUri('base:/jsonapi/block/block/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => 'http://jsonapi.org/format/1.0/',
          ],
        ],
        'version' => '1.0',
      ],
      'links' => [
        'self' => $self_url,
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'block--block',
        'links' => [
          'self' => $self_url,
        ],
        'attributes' => [
          'id' => 'llama',
          'weight' => NULL,
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [
            // @todo Remove this first line in favor of the 3 commented lines in https://www.drupal.org/project/jsonapi/issues/2942979
            // @codingStandardsIgnoreStart
            'classy',
//            'theme' => [
//              'classy',
//            ],
            // @codingStandardsIgnoreEnd
          ],
          'theme' => 'classy',
          'region' => 'header',
          'provider' => NULL,
          'plugin' => 'llama_block',
          'settings' => [
            'id' => 'broken',
            'label' => '',
            'provider' => 'core',
            'label_display' => 'visible',
          ],
          'visibility' => [],
          'uuid' => $this->entity->uuid(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    // @todo Update once https://www.drupal.org/node/2300677 is fixed.
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags() {
    // Because the 'user.permissions' cache context is missing, the cache tag
    // for the anonymous user role is never added automatically.
    return array_values(array_diff(parent::getExpectedCacheTags(), ['config:user.role.anonymous']));
  }

  // @codingStandardsIgnoreStart
  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    // @see ::createEntity()
    // @todo Uncomment first line, remove second line in https://www.drupal.org/project/jsonapi/issues/2940342.
//    return ['url.site'];
    return parent::getExpectedCacheContexts();
  }
  // @codingStandardsIgnoreEnd

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return '';

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    // @see \Drupal\block\BlockAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedAccessCacheability()
      ->setCacheTags([
        '4xx-response',
        'config:block.block.llama',
        'http_response',
        'user:2',
      ])
      ->setCacheContexts(['user.roles']);
  }

}
