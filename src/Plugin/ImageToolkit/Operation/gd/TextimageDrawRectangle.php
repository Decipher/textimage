<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDrawRectangle.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageDrawRectangleTrait;

/**
 * Defines Textimage GD2 draw rectangle operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_draw_rectangle",
 *   toolkit = "gd",
 *   operation = "textimage_draw_rectangle",
 *   label = @Translation("Textimage Draw Rectangle"),
 *   description = @Translation("Draws on the image a rectangle, optionally filling it in with a specified color.")
 * )
 */
class TextimageDrawRectangle extends GDTextimageOperationBase {

  use TextimageDrawRectangleTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $success = TRUE;
    if ($arguments['fill_color']) {
      $color = $this->allocateColorFromRgba($arguments['fill_color']);
      $success = imagefilledpolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), 4, $color);
    }
    if ($success && $arguments['border_color']) {
      $color = $this->allocateColorFromRgba($arguments['border_color']);
      $success = imagepolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), 4, $color);
    }
    return $success;
  }

}
