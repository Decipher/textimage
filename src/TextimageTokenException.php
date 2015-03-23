<?php

/**
 * @file
 * Contains \Drupal\textimage\TextimageTokenException.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\SafeMarkup;

/**
 * Exception thrown by Textimage factory on token processing failure.
 */
class TextimageTokenException extends \Exception {

  /**
   * The failing token.
   *
   * @var string
   */
  protected $token;

  /**
   * Constructs a TextimageImagerTokenException object.
   */
  public function __construct($token, \Exception $previous = NULL) {
      parent::__construct(SafeMarkup::format("Textimage token @token could not be resolved.", array('@token' => $token)), 0, $previous);
      $this->token = $token;
  }

  /**
   * Gets failing token.
   */
  public function getToken() {
    return $this->token;
  }

}
