<?php

declare(strict_types=1);

namespace Drupal\textimage;

/**
 * Exception thrown by Textimage factory on token processing failure.
 */
class TextimageTokenException extends \Exception {

  public function __construct(
    protected readonly string $token,
    ?\Exception $previous = NULL,
  ) {
    parent::__construct("Textimage token {$token} could not be resolved.", 0, $previous);
  }

  /**
   * Gets failing token.
   */
  public function getToken(): string {
    return $this->token;
  }

}
