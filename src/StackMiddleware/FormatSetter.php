<?php

namespace Drupal\jsonapi\StackMiddleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * If the request belongs to a JSON API managed route, then sets the api_json
 * format manually.
 */
class FormatSetter implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a PageCache object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    // Check if the accept header is set to the official header.
    $content_types = array_filter($request->getAcceptableContentTypes(), function ($accept) {
      return strpos($accept, 'application/vnd.api+json') !== FALSE;
    });
    if (count($content_types)) {
      // Manually set the format.
      $request->setRequestFormat('api_json');
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

}
