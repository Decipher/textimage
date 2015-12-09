<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\TextimageDrawRectangle.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageDrawRectangleTrait;

/**
 * Defines Textimage Imagemagick draw rectangle operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_imagemagick_textimage_draw_rectangle",
 *   toolkit = "imagemagick",
 *   operation = "textimage_draw_rectangle",
 *   label = @Translation("Textimage Draw Rectangle"),
 *   description = @Translation("Draws on the image a rectangle, optionally filling it in with a specified color.")
 * )
 */
class TextimageDrawRectangle extends ImagemagickTextimageOperationBase {

  use TextimageDrawRectangleTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $arg = '';
    if ($arguments['fill_color']) {
      $arg .= '-fill ' . $this->getToolkit()->escapeShellArg($arguments['fill_color']);
    }
    else {
      $arg .= '-fill none';
    }
    if ($arguments['border_color']) {
      $arg .= ' -stroke ' . $this->getToolkit()->escapeShellArg($arguments['border_color']) . ' -strokewidth 1';
    }
    $d = $arguments['rectangle']->getPoint('c_d');
    $b = $arguments['rectangle']->getPoint('c_b');
    $this->getToolkit()->addArgument($arg . ' -draw ' . $this->getToolkit()->escapeShellArg("rectangle {$d[0]},{$d[1]} {$b[0]},{$b[1]}"));
    return TRUE;
  }

}
