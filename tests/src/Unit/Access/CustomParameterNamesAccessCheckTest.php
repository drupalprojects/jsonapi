<?php

namespace Drupal\Tests\jsonapi\Unit\Access;

use Drupal\jsonapi\Access\CustomParameterNamesAccessCheck;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\jsonapi\Access\CustomParameterNamesAccessCheck
 * @group jsonapi
 */
class CustomParameterNamesAccessCheckTest extends \PHPUnit_Framework_TestCase {

  /**
   * @dataProvider providerTestJsonApiParamsValidation
   * @covers ::access
   * @covers ::validate
   */
  public function testJsonApiParamsValidation($name, $valid) {
    $access_checker = new CustomParameterNamesAccessCheck();

    $request = new Request();
    $request->attributes->set('_json_api_params', [$name => '123']);
    $result = $access_checker->access($request);

    if ($valid) {
      $this->assertTrue($result->isAllowed());
    }
    else {
      $this->assertFalse($result->isAllowed());
    }
  }

  public function providerTestJsonApiParamsValidation() {
    $data = [];

    $data['Valid member, but invalid custom query parameter'] = ['foobar', FALSE];
    $data['Valid custom query parameter: dash'] = ['foo-bar', TRUE];
    $data['Valid custom query parameter: underscore'] = ['foo_bar', TRUE];
    $data['Valid custom query parameter: camelcase'] = ['fooBar', TRUE];

    return $data;
  }

}
