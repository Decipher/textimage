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

    // Add arguments.
    $this->getToolkit()->addArgument('-gravity none');
    if ($arguments['background_color']) {
      $this->getToolkit()->addArgument('-background ' . $this->getToolkit()->escapeShellArg($arguments['background_color']));
    } // @todo transparency
    $this->getToolkit()
      ->addArgument('-compose src-over')
      ->addArgument('-extent ' . $geometry);

    // Set dimensions.
    $this->getToolkit()
      ->setWidth($targetsize['width'])
      ->setHeight($targetsize['height']);

    return TRUE;
  }

}
