<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\TextimageDefineCanvas.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageDefineCanvasTrait;

/**
 * Defines Textimage Imagemagick define canvas operation.
 *
 * Much of the code in this class is taken from the imagecache_action module
 * for Drupal 7.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_imagemagick_textimage_define_canvas",
 *   toolkit = "imagemagick",
 *   operation = "textimage_define_canvas",
 *   label = @Translation("Textimage define canvas image"),
 *   description = @Translation("Defines a canvas for the image.")
 * )
 */
class TextimageDefineCanvas extends ImagemagickTextimageOperationBase {

  use TextimageDefineCanvasTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $targetsize = $arguments['targetsize'];

    // Calculate geometry.
    $geometry = sprintf('%dx%d', $targetsize['width'], $targetsize['height']);
    if ($targetsize['left'] || $targetsize['top']) {
      $geometry .= sprintf('%+d%+d', -$targetsize['left'], -$targetsize['top']);
    }

    // Determine background.
    if ($arguments['background_color']) {
      $bg = '-background ' . $this->getToolkit()->escapeShellArg($arguments['background_color']);
    }
    else {
      $bg = '-background transparent';
    }

    // Add argument.
    $this->getToolkit()->addArgument("-gravity none {$bg} -compose src-over -extent {$geometry}");

    // Set dimensions.
    $this->getToolkit()
      ->setWidth($targetsize['width'])
      ->setHeight($targetsize['height']);

    return TRUE;
  }

}
