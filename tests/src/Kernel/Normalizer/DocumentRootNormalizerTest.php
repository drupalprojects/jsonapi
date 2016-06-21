<?php

namespace Drupal\Tests\jsonapi\Kernel\Normalizer;

use Drupal\Component\Serialization\Json;
use Drupal\jsonapi\LinkManager\LinkManagerInterface;
use Drupal\jsonapi\Normalizer\DocumentRootNormalizerInterface;
use Drupal\jsonapi\Resource\DocumentWrapper;
use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;
use Prophecy\Argument;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class DocumentRootNormalizerTest.
 *
 * @package Drupal\jsonapi\Normalizer
 *
 * @coversDefaultClass \Drupal\jsonapi\Normalizer\ContentEntityNormalizer
 *
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
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('fields')->willReturn([
      'node--article' => 'title,type,uid',
      'user--user' => 'name',
    ]);
    $query->get('include')->willReturn('uid');
    $query->getIterator()->willReturn(new \ArrayIterator());
    $request->query = $query->reveal();
    $route = $this->prophesize(Route::class);
    $route->getPath()->willReturn('/node/article/{node}');
    $route->getRequirement('_entity_type')->willReturn('node');
    $route->getRequirement('_bundle')->willReturn('article');
    $request->get(RouteObjectInterface::ROUTE_OBJECT)->willReturn($route->reveal());
    $document_wrapper = $this->prophesize(DocumentWrapper::class);
    $document_wrapper->getData()->willReturn($this->node);
    $resource_config = $this->prophesize(ResourceConfigInterface::CLASS);
    $resource_config->getTypeName()->willReturn('node--article');

    // Make sure the route contains the entity type and bundle.
    $current_context = $this->container->get('jsonapi.current_context');
    $current_context->setCurrentRoute($route->reveal());

    $this->container->set('jsonapi.current_context', $current_context);
    $this->container->get('serializer');
    $normalized = $this
      ->container
      ->get('serializer.normalizer.document_root.jsonapi')
      ->normalize(
        $document_wrapper->reveal(),
        'api_json',
        [
          'request' => $request->reveal(),
          'resource_config' => $resource_config->reveal(),
        ]
      );
    $this->assertSame($normalized['data']['attributes']['title'], 'dummy_title');
    $this->assertEquals($normalized['data']['id'], 1);
    $this->assertSame([
      'data' => [
        'type' => 'node_type--node_type',
        'id' => 'article',
      ],
      'links' => [
        'self' => 'dummy_entity_link',
        'related' => 'dummy_entity_link',
      ],
    ], $normalized['data']['relationships']['type']);
    $this->assertTrue(!isset($normalized['data']['attributes']['created']));
    $this->assertSame('node--article', $normalized['data']['type']);
    $this->assertEquals([
      'data' => [
        'type' => 'user--user',
        'id' => $this->user->id(),
      ],
      'links' => [
        'self' => 'dummy_entity_link',
        'related' => 'dummy_entity_link',
      ],
    ], $normalized['data']['relationships']['uid']);
    $this->assertEquals($this->user->id(), $normalized['included'][0]['data']['id']);
    $this->assertEquals('user--user', $normalized['included'][0]['data']['type']);
    $this->assertEquals($this->user->label(), $normalized['included'][0]['data']['attributes']['name']);
    $this->assertTrue(!isset($normalized['included'][0]['data']['attributes']['created']));
  }

  /**
   * @covers ::normalize
   */
  public function testNormalizeNoBundle() {
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('fields')->willReturn([]);
    $query->get('include')->willReturn(NULL);
    $query->getIterator()->willReturn(new \ArrayIterator());
    $request->query = $query->reveal();
    $route = $this->prophesize(Route::class);
    $route->getPath()->willReturn('/node/{node}');
    $route->getRequirement('_entity_type')->willReturn('node');
    $route->getRequirement('_bundle')->willReturn(NULL);
    $request->get(RouteObjectInterface::ROUTE_OBJECT)->willReturn($route->reveal());
    $document_wrapper = $this->prophesize(DocumentWrapper::class);
    $document_wrapper->getData()->willReturn($this->node);
    $resource_config = $this->prophesize(ResourceConfigInterface::CLASS);
    $resource_config->getTypeName()->willReturn('node');

    // Make sure the route contains the entity type.
    $current_context = $this->container->get('jsonapi.current_context');
    $current_context->setCurrentRoute($route->reveal());

    $this->container->set('jsonapi.current_context', $current_context);
    $this->container->get('serializer');
    $normalized = $this
      ->container
      ->get('serializer.normalizer.document_root.jsonapi')
      ->normalize(
        $document_wrapper->reveal(),
        'api_json',
        [
          'request' => $request->reveal(),
          'resource_config' => $resource_config->reveal(),
        ]
      );
    $this->assertSame($normalized['data']['attributes']['title'], 'dummy_title');
    $this->assertEquals($normalized['data']['id'], 1);
    $this->assertSame([
      'data' => [
        'type' => 'node_type--node_type',
        'id' => 'article',
      ],
      'links' => [
        'self' => 'dummy_entity_link',
        'related' => 'dummy_entity_link',
      ],
    ], $normalized['data']['relationships']['type']);
    $this->assertTrue(isset($normalized['data']['attributes']['created']));
    // The body field and field_tags are attached to the bundle, so they should not be present here.
    $this->assertTrue(!isset($normalized['data']['attributes']['body']));
    $this->assertTrue(!isset($normalized['data']['attributes']['field_tags']));
    $this->assertSame('node', $normalized['data']['type']);
  }

  /**
   * @covers ::normalize
   */
  public function testNormalizeConfig() {
    $request = $this->prophesize(Request::class);
    $query = $this->prophesize(ParameterBag::class);
    $query->get('fields')->willReturn([
      'node_type--node_type' => 'uuid,display_submitted',
    ]);
    $query->get('include')->willReturn(NULL);
    $query->getIterator()->willReturn(new \ArrayIterator());
    $request->query = $query->reveal();
    $route = $this->prophesize(Route::class);
    $route->getPath()->willReturn('/node_type/node_type/{node_type}');
    $route->getRequirement('_entity_type')->willReturn('node');
    $route->getRequirement('_bundle')->willReturn('article');
    $request->get(RouteObjectInterface::ROUTE_OBJECT)->willReturn($route->reveal());
    $document_wrapper = $this->prophesize(DocumentWrapper::class);
    $document_wrapper->getData()->willReturn($this->nodeType);
    $resource_config = $this->prophesize(ResourceConfigInterface::CLASS);
    $resource_config->getTypeName()->willReturn('node_type--node_type');

    // Make sure the route contains the entity type and bundle.
    $current_context = $this->container->get('jsonapi.current_context');
    $current_context->setCurrentRoute($route->reveal());

    $this->container->set('jsonapi.current_context', $current_context);
    $this->container->get('serializer');
    $normalized = $this
      ->container
      ->get('serializer.normalizer.document_root.jsonapi')
      ->normalize($document_wrapper->reveal(), 'api_json', [
        'request' => $request->reveal(),
        'resource_config' => $resource_config->reveal(),
      ]);
    $this->assertTrue(empty($normalized['data']['attributes']['type']));
    $this->assertTrue(!empty($normalized['data']['attributes']['uuid']));
    $this->assertSame($normalized['data']['attributes']['display_submitted'], TRUE);
    $this->assertSame($normalized['data']['id'], 'article');
    $this->assertSame($normalized['data']['type'], 'node_type--node_type');
  }

  /**
   * Try to POST a node and check if it exists afterwards.
   *
   * @covers ::denormalize
   */
  public function testDenormalize() {
    $payload = '{"type":"article", "data":{"attributes":{"title":"Testing article"}}}';
    $request = $this->prophesize(Request::class);
    $route = $this->prophesize(Route::class);
    $route->getPath()->willReturn('/node/article');
    $route->getRequirement('_entity_type')->willReturn('node');
    $route->getRequirement('_bundle')->willReturn('article');
    $route->getDefault('_on_relationship')->willReturn(NULL);
    $request->get(RouteObjectInterface::ROUTE_OBJECT)->willReturn($route->reveal());
    $resource_config = $this->prophesize(ResourceConfigInterface::CLASS);
    $resource_config->getTypeName()->willReturn('node--article');
    $resource_config->getEntityTypeId()->willReturn('node');
    $resource_config->getBundleId()->willReturn('article');
    $resource_config->getDeserializationTargetClass()->willReturn('Drupal\node\Entity\Node');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $resource_config->getStorage()->willReturn($entity_type_manager->getStorage('node'));
    /* @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request->reveal());
    $this->container->set('request_stack', $request_stack);
    $this->container->get('serializer');
    $node = $this
      ->container
      ->get('serializer.normalizer.document_root.jsonapi')
      ->denormalize(Json::decode($payload), DocumentRootNormalizerInterface::class, 'api_json', [
        'request' => $request->reveal(),
        'resource_config' => $resource_config->reveal(),
      ]);
    $this->assertInstanceOf('\Drupal\node\Entity\Node', $node);
    $this->assertSame('Testing article', $node->getTitle());
  }

}
