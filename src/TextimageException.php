<?php

declare(strict_types=1);

namespace Drupal\textimage;

/**
 * Exception thrown by Textimage on failure.
 */
class TextimageException extends \Exception {

  public function __construct(string $message, ?\Exception $previous = NULL) {
    parent::__construct("Textimage error: {$message}", 0, $previous);
  }

}
