<?php

namespace Drupal\Tests\jsonapi\Kernel\Configuration;

use Drupal\jsonapi\Configuration\ResourceConfigInterface;
use Drupal\jsonapi\Configuration\ResourceManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Class ResourceManagerTest.
 *
 * @package Drupal\Tests\jsonapi\Kernel\Resource
 *
 * @coversDefaultClass \Drupal\jsonapi\Configuration\ResourceManager
 *
 * @group jsonapi
 */
class ResourceManagerTest extends KernelTestBase {

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
   * The entity resource under test.
   *
   * @var \Drupal\jsonapi\Configuration\ResourceManagerInterface
   */
  protected $resourceManager;

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
    NodeType::create([
      'type' => 'article',
    ])->save();
    NodeType::create([
      'type' => 'page',
    ])->save();

    $this->resourceManager = $this->container->get('jsonapi.resource.manager');
  }

  /**
   * @covers ::all
   */
  public function testAll() {
    // Make sure that there are resources being created.
    $all = $this->resourceManager->all();
    $this->assertNotEmpty($all);
    // Get a random resource config.
    $resource_config = $all[mt_rand(0, count($all) - 1)];
    $this->assertNotEmpty($resource_config->getDeserializationTargetClass());
    $this->assertNotEmpty($resource_config->getEntityTypeId());
    $this->assertNotEmpty($resource_config->getBundleId());
    $this->assertNotEmpty($resource_config->getGlobalConfig());
    $this->assertNotEmpty($resource_config->getPath());
    $this->assertNotEmpty($resource_config->getTypeName());
  }

  /**
   * @covers ::get
   */
  public function testGet() {
    // Make sure that there are resources being created.
    $resource_config = $this->resourceManager->get('node', 'article');
    $this->assertInstanceOf(ResourceConfigInterface::class, $resource_config);
    $this->assertSame('Drupal\node\Entity\Node', $resource_config->getDeserializationTargetClass());
    $this->assertSame('node', $resource_config->getEntityTypeId());
    $this->assertSame('article', $resource_config->getBundleId());
    $this->assertNotEmpty($resource_config->getGlobalConfig());
    $this->assertSame('/article', $resource_config->getPath());
    $this->assertSame('article', $resource_config->getTypeName());
  }

}
