<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Drupal\jsonapi\Resource\DocumentWrapper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class DocumentRootNormalizerTest.
 *
 * @package Drupal\jsonapi\Normalizer
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\ContentEntityNormalizer
 * @group jsonapi
 */
class DocumentRootNormalizerTest extends KernelTestBase {

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
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
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
      'type' => 'article',
      'uid' => 1,
    ]);

    $this->node->save();

    $link_manager = $this->prophesize(LinkManagerInterface::class);
    $link_manager
      ->getEntityLink(Argument::any(), Argument::any(), Argument::type('array'), Argument::type('string'))
      ->willReturn('dummy_entity_link');
    $link_manager
      ->getRequestLink(Argument::any())
      ->willReturn('dummy_document_link');
    $this->container->set('jsonapi.link_manager', $link_manager->reveal());

    $this->nodeType = NodeType::load('article');
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
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('fields')->willReturn([
      'article' => 'title,type,uid',
      'user' => 'name',
    ]);
    $query->get('include')->willReturn('uid');
    $query->getIterator()->willReturn(new \ArrayIterator());
    $request->query = $query->reveal();
    $route = $this->prophesize(Route::class);
    $route->getPath()->willReturn('/node/{node}');
    $request->get('_route_object')->willReturn($route->reveal());
    $document_wrapper = $this->prophesize(DocumentWrapper::class);
    $document_wrapper->getData()->willReturn($this->node);
    $normalized = $this
      ->container
      ->get('serializer.normalizer.document_root.jsonapi')
      ->normalize($document_wrapper->reveal(), 'api_json', ['request' => $request->reveal()]);
    $this->assertSame($normalized['data']['attributes']['title'], 'dummy_title');
    $this->assertEquals($normalized['data']['id'], 1);
    $this->assertSame([
      'type' => 'node_type',
      'id' => 'article',
    ], $normalized['data']['relationships']['type']);
    $this->assertTrue(!isset($normalized['data']['attributes']['created']));
    $this->assertSame('article', $normalized['data']['type']);
    $this->assertEquals([
      'data' => [
        'type' => 'user',
        'id' => $this->user->id(),
      ],
      'links' => [
        'self' => 'dummy_entity_link',
        'related' => 'dummy_entity_link',
      ],
    ], $normalized['data']['relationships']['uid']);
    $this->assertEquals($this->user->id(), $normalized['included'][0]['data']['id']);
    $this->assertEquals('user', $normalized['included'][0]['data']['type']);
    $this->assertEquals($this->user->label(), $normalized['included'][0]['data']['attributes']['name']);
    $this->assertTrue(!isset($normalized['included'][0]['data']['attributes']['created']));
  }

  /**
   * @covers ::normalize
   */
  public function testNormalizeConfig() {
    $this->container->get('serializer');
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get(Argument::any())->willReturn(NULL);
    $query->getIterator()->willReturn(new \ArrayIterator());
    $request->query = $query->reveal();
    $route = $this->prophesize(Route::class);
    $route->getPath()->willReturn('/node_type/{node_type}');
    $request->get('_route_object')->willReturn($route->reveal());
    $document_wrapper = $this->prophesize(DocumentWrapper::class);
    $document_wrapper->getData()->willReturn($this->nodeType);
    $normalized = $this
      ->container
      ->get('serializer.normalizer.document_root.jsonapi')
      ->normalize($document_wrapper->reveal(), 'api_json', ['request' => $request->reveal()]);
    $this->assertSame($normalized['data']['attributes']['type'], 'article');
    $this->assertSame($normalized['data']['attributes']['display_submitted'], TRUE);
  }

}
