<?php

namespace Drupal\jsonapi\Error;

/**
 * @see http://jsonapi.org/format/#errors
 *
 * @see \Drupal\jsonapi\Controller\RequestHandler::renderJsonApiResponse
 * @internal
 */
class ErrorHandler {

  /**
   * Register the handler.
   */
  public function register() {
    set_error_handler(get_called_class() . '::handle');
  }

  /**
   * Go back to normal and restore the previous error handler.
   */
  public function restore() {
    restore_error_handler();
  }

  /**
   * Handle the PHP error with custom business logic.
   *
   * @param $error_level
   *   The level of the error raised.
   * @param $message
   *   The error message.
   * @param $filename
   *   The filename that the error was raised in.
   * @param $line
   *   The line number the error was raised at.
   * @param $context
   *   An array that points to the active symbol table at the point the error
   *   occurred.
   */
  public static function handle($error_level, $message, $filename, $line, $context) {
    $message = 'Unexpected PHP error: ' . $message;
    _drupal_error_handler($error_level, $message, $filename, $line, $context);
    $types = drupal_error_levels();
    list($severity_msg, $severity_level) = $types[$error_level];
    // Only halt execution if the error is more severe than a warning.
    if ($severity_level < 4) {
      throw new SerializableHttpException(500, sprintf('[%s] %s', $severity_msg, $message), NULL, [], $error_level);
    }
  }

}
