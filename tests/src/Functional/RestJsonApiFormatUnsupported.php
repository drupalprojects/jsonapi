<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\rest\Functional\ResourceTestBase;

/**
 * @group jsonapi
 */
class RestJsonApiFormatUnsupported extends ResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['jsonapi', 'node'];

  /**
   * {@inheritdoc}
   */
  protected static $format = 'api_json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/vnd.api+json';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['access content']);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Set up a HTTP client that accepts relative URLs.
    $this->httpClient = $this->container->get('http_client_factory')
      ->fromOptions(['base_uri' => $this->baseUrl]);

    // Create a "Camelids" node type.
    NodeType::create([
      'name' => 'Camelids',
      'type' => 'camelids',
    ])->save();

    // Create a "Llama" node.
    $node = Node::create(['type' => 'camelids']);
    $node->setTitle('Llama')
      ->setOwnerId(0)
      ->setPublished(TRUE)
      ->save();
  }

  /**
   * Tests that JSON API is not supported
   */
  public function testJsonApiFormatNotSupportedInRest() {
    // First, verify that 'api_json' does not appear in the 'serializer.foramts'
    // container parameter, which is what the REST module uses to determine
    // which formats it supports.
    $this->assertSame(['json', 'xml'], $this->container->getParameter('serializer.formats'));

    // Second, verify that provisioning a REST resource that lists 'api_json' as
    // one of its formats
    $this->provisionResource('entity.node', ['api_json'], []);
    $this->setUpAuthorization('GET');
    $url = Node::load(1)->toUrl()->setOption('query', ['_format' => 'api_json']);
    $response = $this->request('GET', $url, []);
    $expected_body = Json::encode([
      'errors' => [
        [
          'title' => 'Not Acceptable',
          'status' => 406,
          'detail' => 'Not acceptable format: api_json',
          'links' => [
            'info' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.7',
          ],
          'code' => 0,
        ],
      ],
    ]);
    $this->assertResourceResponse(406, $expected_body, $response);
  }

  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options) {}
  protected function getExpectedUnauthorizedAccessMessage($method) {}
  protected function getExpectedBcUnauthorizedAccessMessage($method) {}

}
