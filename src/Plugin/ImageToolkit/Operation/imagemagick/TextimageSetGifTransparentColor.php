<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\TextimageSetGifTransparentColor.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageSetGifTransparentColorTrait;

/**
 * Defines ImageMagick textimage set_gif_transparent_color image operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_imagemagick_textimage_set_gif_transparent_color",
 *   toolkit = "imagemagick",
 *   operation = "textimage_set_gif_transparent_color",
 *   label = @Translation("Set the image transparent color"),
 *   description = @Translation("Set the image transparent color for GIF images.")
 * )
 */
class TextimageSetGifTransparentColor extends ImagemagickTextimageOperationBase {

  use TextimageSetGifTransparentColorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $format = $this->getToolkit()->getDestinationFormat() ?: $this->getToolkit()->getSourceFormat();
    if (strpos($format, 'GIF') === 0 && $arguments['transparent_color']) {
      $index = $this->getToolkit()->findArgument('-transparent-color');
      if ($index !== FALSE) {
        $this->getToolkit()->removeArgument($index);
      }
      $this->getToolkit()->addArgument('-transparent-color ' . $this->getToolkit()->escapeShellArg($arguments['transparent_color']));
    }
  }

}
