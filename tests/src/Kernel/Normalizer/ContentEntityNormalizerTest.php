<?php

namespace Drupal\jsonapi\Test\Kernel\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Normalizer\ContentEntityNormalizer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Drupal\user\Entity\User;

/**
 * Class ContentEntityNormalizer.
 *
 * @package Drupal\jsonapi\Normalizer
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\ContentEntityNormalizer
 * @group jsonapi
 */
class ContentEntityNormalizerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'jsonapi',
    'rest',
    'serialization',
    'system',
    'user',
  ];

  /**
   * A node to normalize.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $node;

  /**
   * A user to normalize.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $type = NodeType::create([
      'type' => 'article',
    ]);
    $type->save();
    $this->user = User::create([
      'name' => 'user1',
      'mail' => 'user@localhost',
    ]);
    $this->user->save();
    $this->node = Node::create([
      'title' => 'dummy_title',
      'body' => 'dummy_body',
      'type' => 'article',
      'uid' => 1,
    ]);

    $this->node->save();
  }


  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    if ($this->node) {
      $this->node->delete();
    }
    if ($this->user) {
      $this->user->delete();
    }
  }

  /**
   * @covers ::normalize
   */
  public function testNormalize() {
    $this->container->get('serializer');
    $normalized = $this
      ->container
      ->get('serializer.normalizer.entity.jsonapi')
      ->normalize($this->node, 'api_json', [
        'resource_path' => 'node',
      ]);
    $this->assertEquals(NULL, $normalized);
  }

}
