<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\RequestOptions;

/**
 * JSON API regression tests.
 *
 * @group jsonapi
 * @group legacy
 *
 * @internal
 */
class JsonApiRegressionTest extends JsonApiFunctionalTestBase {

  use CommentTestTrait;

  /**
   * Ensure filtering on relationships works with bundle-specific target types.
   *
   * @see https://www.drupal.org/project/jsonapi/issues/2953207
   */
  public function testBundleSpecificTargetEntityTypeFromIssue2953207() {
    // Set up data model.
    $this->assertTrue($this->container->get('module_installer')->install(['comment'], TRUE), 'Installed modules.');
    $this->addDefaultCommentField('taxonomy_term', 'tags', 'comment', CommentItemInterface::OPEN, 'tcomment');
    $this->rebuildAll();

    // Create data.
    Term::create([
      'name' => 'foobar',
      'vid' => 'tags',
    ])->save();
    Comment::create([
      'subject' => 'Llama',
      'entity_id' => 1,
      'entity_type' => 'taxonomy_term',
      'field_name' => 'comment',
    ])->save();

    // Test.
    $user = $this->drupalCreateUser([
      'access comments',
    ]);
    $response = $this->request('GET', Url::fromUri('internal:/jsonapi/comment/tcomment?include=entity_id&filter[entity_id.name]=foobar'), [
      RequestOptions::AUTH => [
        $user->getUsername(),
        $user->pass_raw,
      ],
    ]);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Ensure deep nested include works on multi target entity type field.
   *
   * @see https://www.drupal.org/project/jsonapi/issues/2973681
   */
  public function testDeepNestedIncludeMultiTargetEntityTypeFieldFromIssue2973681() {
    // Set up data model.
    $this->assertTrue($this->container->get('module_installer')->install(['comment'], TRUE), 'Installed modules.');
    $this->addDefaultCommentField('node', 'article');
    $this->addDefaultCommentField('taxonomy_term', 'tags', 'comment', CommentItemInterface::OPEN, 'tcomment');
    $this->drupalCreateContentType(['type' => 'page']);
    $this->rebuildAll();

    $this->createEntityReferenceField(
      'node',
      'page',
      'field_comment',
      NULL,
      'comment',
      'default',
      [
        'target_bundles' => [
          'comment' => 'comment',
          'tcomment' => 'tcomment',
        ],
      ],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    // Create data.
    $node = Node::create([
      'title' => 'test article',
      'type' => 'article',
    ]);
    $node->save();
    $comment = Comment::create([
      'subject' => 'Llama',
      'entity_id' => 1,
      'entity_type' => 'node',
      'field_name' => 'comment',
    ]);
    $comment->save();
    $page = Node::create([
      'title' => 'test node',
      'type' => 'page',
      'field_comment' => [
        'entity' => $comment,
      ],
    ]);
    $page->save();

    // Test.
    $user = $this->drupalCreateUser([
      'access content',
      'access comments',
    ]);
    $response = $this->request('GET', Url::fromUri('internal:/jsonapi/node/page?include=field_comment,field_comment.entity_id,field_comment.entity_id.uid'), [
      RequestOptions::AUTH => [
        $user->getUsername(),
        $user->pass_raw,
      ],
    ]);
    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Ensure POST and PATCH works for bundle-less relationship routes.
   *
   * @see https://www.drupal.org/project/jsonapi/issues/2976371
   */
  public function testBundlelessRelationshipMutationFromIssue2973681() {
    // Set up data model.
    $this->drupalCreateContentType(['type' => 'page']);
    $this->createEntityReferenceField(
      'node',
      'page',
      'field_test',
      NULL,
      'user',
      'default',
      [
        'target_bundles' => NULL,
      ],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
    $this->rebuildAll();

    // Create data.
    $node = Node::create([
      'title' => 'test article',
      'type' => 'page',
    ]);
    $node->save();
    $target = $this->createUser();

    // Test.
    $user = $this->drupalCreateUser(['bypass node access']);
    $url = Url::fromRoute('jsonapi.node--page.relationship', ['node' => $node->uuid(), 'related' => 'field_test']);
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/vnd.api+json',
        'Accept' => 'application/vnd.api+json',
      ],
      RequestOptions::AUTH => [$user->getUsername(), $user->pass_raw],
      RequestOptions::JSON => [
        'data' => [
          ['type' => 'user--user', 'id' => $target->uuid()],
        ],
      ],
    ];
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
  }

}
