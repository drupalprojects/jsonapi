<?php

namespace Drupal\Tests\jsonapi\Kernel\Controller;

use Drupal\jsonapi\ResourceResponse;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Controller\RequestHandler
 * @group jsonapi
 */
class RequestHandlerTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'field',
    'jsonapi',
    'serialization',
    'system',
    'user',
  ];

  public function testResponseValidation() {
    // Check that the validation class is enabled.
    $this->assertTrue(
      class_exists("\\JsonSchema\\Validator"),
      'The JSON Schema validator is not present. Please make sure to install it using composer.'
    );

    // Expose the protected RequestHandler::validateResponse() method.
    $class = new \ReflectionClass('Drupal\jsonapi\Controller\RequestHandler');
    $validate_response = $class->getMethod('validateResponse');
    $validate_response->setAccessible(TRUE);

    // Test validation failure: no "type" in "data".
    $json = <<<'EOD'
{
  "data": {
    "id": "4f342419-e668-4b76-9f87-7ce20c436169",
    "attributes": {
      "nid": "1",
      "uuid": "4f342419-e668-4b76-9f87-7ce20c436169"
    }
  }
}
EOD;
    $response = new ResourceResponse();
    $response->setContent($json);
    $this->assertFalse(
      $validate_response->invoke(NULL, $response),
      'Response validation failed to flag an invalid response.'
    );

    // Test validation failure: no "data" and "errors" at the root level.
    $json = <<<'EOD'
{
  "data": {
    "type": "node--article",
    "id": "4f342419-e668-4b76-9f87-7ce20c436169",
    "attributes": {
      "nid": "1",
      "uuid": "4f342419-e668-4b76-9f87-7ce20c436169"
    }
  },
  "errors": [{}]
}
EOD;
    $response = new ResourceResponse();
    $response->setContent($json);
    $this->assertFalse(
      $validate_response->invoke(NULL, $response),
      'Response validation failed to flag an invalid response.'
    );

    // Test validation success.
    $json = <<<'EOD'
{
  "data": {
    "type": "node--article",
    "id": "4f342419-e668-4b76-9f87-7ce20c436169",
    "attributes": {
      "nid": "1",
      "uuid": "4f342419-e668-4b76-9f87-7ce20c436169"
    }
  }
}
EOD;
    $response->setContent($json);
    $this->assertTrue(
      $validate_response->invoke(NULL, $response),
      'Response validation flagged a valid response.'
    );

    // Test validation of an empty response passes.
    $response = new ResourceResponse();
    $this->assertTrue(
      $validate_response->invoke(NULL, $response),
      'Response validation flagged a valid empty response.'
    );

  }


}
