<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\TextimageOverlay.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOverlayTrait;

/**
 * Defines Textimage Imagemagick overlay operation.
 *
 * Much of the code in this class is taken from the imagecache_action module
 * for Drupal 7.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_imagemagick_textimage_overlay",
 *   toolkit = "imagemagick",
 *   operation = "textimage_overlay",
 *   label = @Translation("Textimage overlay image"),
 *   description = @Translation("Overlays an image over or under the current.")
 * )
 */
class TextimageOverlay extends ImagemagickTextimageOperationBase {

  use TextimageOverlayTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // In Imagemagick terms:
    // - $this is the destination (the image being constructed).
    // - $arguments['layer'] is the source (the source of the current
    //   operation).
    // Add the layer image to the Imagemagick command line.
    if (!$source_path = $arguments['layer']->getToolkit()->getSourceLocalPath()) {
      return FALSE;
    }
    $source = $this->getToolkit()->escapeShellArg($source_path);

    // Set offset. Offset arguments require a sign in front.
    $x = ($arguments['x'] >= 0) ? "+" . $arguments['x'] : $arguments['x'];
    $y = ($arguments['y'] >= 0) ? "+" . $arguments['y'] : $arguments['y'];

    // Add argument, compose the layer with the destination.
    $this->getToolkit()->addArgument("-gravity none {$source} -geometry {$x}{$y} -compose src-over -composite");

    return TRUE;
  }

}
