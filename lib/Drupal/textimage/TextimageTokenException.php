<?php

/**
 * @file
 * Contains \Drupal\textimage\TextimageTokenException.
 */

namespace Drupal\textimage;

// @todo remove t()

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
      parent::__construct(t("Textimage token @token could not be resolved.", array('@token' => $token)), 0, $previous);
      $this->token = $token;
  }

  /**
   * Gets failing token.
   */
  public function getToken() {
    return $this->token;
  }

}
