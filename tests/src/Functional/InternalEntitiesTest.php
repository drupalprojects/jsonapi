<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\jsonapi_entity_test\Entity\EntityTestNoLabel;
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
    'jsonapi_entity_test',
    'serialization',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

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
      'jsonapi_entity_test_no_label'
    );
    $internal_entity = EntityTestNoLabel::create([]);
    $internal_entity->save();
    $this->referencingEntity = EntityTestWithBundle::create([
      'type' => 'internal_referencer',
      'field_internal' => $internal_entity->id(),
    ]);
    $this->referencingEntity->save();
    drupal_flush_all_caches();
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
    $response = $this->drupalGet($path);
    $decoded = Json::decode($response);
    $this->assertArrayNotHasKey(
      'included',
      $decoded,
      'Internal entities should not be included in compound documents.'
    );
  }

}
