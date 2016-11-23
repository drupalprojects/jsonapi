<?php

namespace Drupal\jsonapi\EventSubscriber;

use Drupal\jsonapi\Error\SerializableHttpException;
use Drupal\serialization\EventSubscriber\DefaultExceptionSubscriber as SerializationDefaultExceptionSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DefaultExceptionSubscriber extends SerializationDefaultExceptionSubscriber {

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return parent::getPriority() + 25;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['api_json'];
  }

  /**
   * {@inheritdoc}
   */
  public function onException(GetResponseForExceptionEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Exception\HttpException $exception */
    $exception = $event->getException();
    $format = $event->getRequest()->getRequestFormat();
    if (!$this->serializer->supportsEncoding($format)) {
      return;
    }
    if (!$exception instanceof HttpException) {
      $exception = new SerializableHttpException(500, $exception->getMessage(), $exception);
      $event->setException($exception);
    }

    $this->setEventResponse($event, $exception->getStatusCode());
  }

  /**
   * {@inheritdoc}
   */
  protected function setEventResponse(GetResponseForExceptionEvent $event, $status) {
    /** @var \Symfony\Component\HttpKernel\Exception\HttpException $exception */
    $exception = $event->getException();
    $format = $event->getRequest()->getRequestFormat();
    if (!$this->serializer->supportsNormalization($exception, $format)) {
      return;
    }
    $encoded_content = $this->serializer->serialize($exception, $format, ['data_wrapper' => 'errors']);
    $response = new Response($encoded_content, $status);
    $event->setResponse($response);
  }

}
