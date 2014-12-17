<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageOverlay.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOverlayTrait;

/**
 * Defines Textimage GD2 overlay operation.
 *
 * Much of the code in this class is taken from the imagecache_action module
 * for Drupal 7.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_overlay",
 *   toolkit = "gd",
 *   operation = "textimage_overlay",
 *   label = @Translation("Textimage overlay image"),
 *   description = @Translation("Overlays an image over or under the current.")
 * )
 */
class TextimageOverlay extends GDTextimageOperationBase {

  use TextimageOverlayTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['layer_on_top']) {
      $width = $arguments['layer']->getWidth();
      $height = $arguments['layer']->getHeight();
      $upper = $arguments['layer']->getToolkit()->getResource();
      $lower = $this->getToolkit()->getResource();
    }
    else {
      $width = $this->getToolkit()->getWidth();
      $height = $this->getToolkit()->getHeight();
      $upper = $this->getToolkit()->getResource();
      $lower = $arguments['layer']->getToolkit()->getResource();
    }
    imagealphablending($lower, TRUE);
    imagesavealpha($lower, TRUE);
    imagealphablending($upper, TRUE);
    imagesavealpha($upper, TRUE);
    imagecopy($lower, $upper, $arguments['x'], $arguments['y'], 0, 0, $width, $height);
    imagedestroy($upper);
    $this->getToolkit()->setResource($lower);
    return TRUE;
  }

}
