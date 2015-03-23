<?php

/**
 * @file
 * Contains \Drupal\textimage\TextimageException.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\SafeMarkup;

/**
 * Exception thrown by Textimage on failure.
 */
class TextimageException extends \Exception {

  /**
   * Constructs a TextimageImagerTokenException object.
   */
  public function __construct($message, \Exception $previous = NULL) {
      parent::__construct(SafeMarkup::format("Textimage error: @message", array('@message' => $message)), 0, $previous);
  }

}
