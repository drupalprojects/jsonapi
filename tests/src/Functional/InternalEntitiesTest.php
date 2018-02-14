<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestNoLabel;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Makes assertions about the JSON API behavior for internal entities.
 *
 * @group jsonapi
 *
 * @internal
 */
class InternalEntitiesTest extends BrowserTestBase {

  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'entity_test',
    'serialization',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * An entity of an internal entity type.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $internalEntity;

  /**
   * An entity referencing an internal entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $referencingEntity;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->testUser = $this->drupalCreateUser([
      'access jsonapi resource list',
      'view test entity',
      'administer entity_test_with_bundle content',
    ], $this->randomString(), TRUE);
    EntityTestBundle::create([
      'id' => 'internal_referencer',
      'label' => 'Entity Test Internal Referencer',
    ])->save();
    $this->createEntityReferenceField(
      'entity_test_with_bundle',
      'internal_referencer',
      'field_internal',
      'Internal Entities',
      'entity_test_no_label'
    );
    $this->internalEntity = EntityTestNoLabel::create([]);
    $this->internalEntity->save();
    $this->referencingEntity = EntityTestWithBundle::create([
      'type' => 'internal_referencer',
      'field_internal' => $this->internalEntity->id(),
    ]);
    $this->referencingEntity->save();
    drupal_flush_all_caches();
  }

  /**
   * Ensures that internal resources types aren't present in the entry point.
   */
  public function testEntryPoint() {
    if (!method_exists(EntityTypeInterface::class, 'isInternal')) {
      $this->markTestSkipped('The Drupal Core version must be >= 8.5');
      return;
    }
    $this->drupalLogin($this->testUser);
    $response = $this->drupalGet('/jsonapi', [], ['Accept' => 'application/vnd.api+json']);
    $decoded = Json::decode($response);
    $this->assertArrayNotHasKey(
      "{$this->internalEntity->getEntityTypeId()}--{$this->internalEntity->bundle()}",
      $decoded['links'],
      'The entry point should not contain links to internal resource type routes.'
    );
  }

  /**
   * Ensures that internal resources types aren't present in the routes.
   */
  public function testRoutes() {
    if (!method_exists(EntityTypeInterface::class, 'isInternal')) {
      $this->markTestSkipped('The Drupal Core version must be >= 8.5');
      return;
    }
    $this->drupalLogin($this->testUser);
    $internal_entity_type_id = $this->internalEntity->getEntityTypeId();
    $internal_bundle = $this->internalEntity->bundle();
    $internal_uuid = $this->internalEntity->uuid();
    $referencing_entity_type_id = $this->referencingEntity->getEntityTypeId();
    $referencing_bundle = $this->referencingEntity->bundle();
    $referencing_uuid = $this->referencingEntity->uuid();
    // This cannot be in a data provider because it needs values created by the
    // setUp method.
    $paths = [
      'individual' => "/jsonapi/{$internal_entity_type_id}/{$internal_bundle}/{$internal_uuid}",
      'collection' => "/jsonapi/{$internal_entity_type_id}/{$internal_bundle}",
      'related' => "/jsonapi/{$referencing_entity_type_id}/{$referencing_bundle}/{$referencing_uuid}/field_internal",
    ];
    foreach ($paths as $type => $path) {
      $response = $this->drupalGet($path, [], ['Accept' => 'application/vnd.api+json']);
      $decoded = Json::decode($response);
      $this->assertSame(
        404,
        $decoded['errors'][0]['status'],
        "The '{$type}' route ({$path}) should not be available for internal resource types.'"
      );
    }
  }

  /**
   * Asserts that internal entities are not included in compound documents.
   */
  public function testIncludes() {
    if (!method_exists(EntityTypeInterface::class, 'isInternal')) {
      $this->markTestSkipped('The Drupal Core version must be >= 8.5');
      return;
    }
    $this->drupalLogin($this->testUser);
    $entity_type_id = $this->referencingEntity->getEntityTypeId();
    $bundle = $this->referencingEntity->bundle();
    $path = "/jsonapi/{$entity_type_id}/{$bundle}/{$this->referencingEntity->uuid()}?include=field_internal";
    $response = $this->drupalGet($path, [], ['Accept' => 'application/vnd.api+json']);
    $decoded = Json::decode($response);
    $this->assertArrayNotHasKey(
      'included',
      $decoded,
      'Internal entities should not be included in compound documents.'
    );
  }

}
