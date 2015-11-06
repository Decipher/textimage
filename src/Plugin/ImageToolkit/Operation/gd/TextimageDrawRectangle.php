<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDrawRectangle.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;
use Drupal\textimage\Component\Rectangle;

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

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'rectangle' => array(
        'description' => 'A Rectangle object.',
      ),
      'fill_color' => array(
        'description' => 'The RGBA color of the polygon fill.',
        'required' => FALSE,
        'default' => NULL,
      ),
      'fill_color_luma' => array(
        'description' => 'If TRUE, convert RGBA of the polygon fill to best match using luma.',
        'required' => FALSE,
        'default' => FALSE,
      ),
      'border_color' => array(
        'description' => 'The RGBA color of the polygon line.',
        'required' => FALSE,
        'default' => NULL,
      ),
      'border_color_luma' => array(
        'description' => 'If TRUE, convert RGBA of the polygon line to best match using luma.',
        'required' => FALSE,
        'default' => FALSE,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure 'rectangle' is an expected Rectangle object.
    if (!$arguments['rectangle'] instanceof Rectangle) {
      throw new \InvalidArgumentException("Rectangle passed to the 'textimage_draw_rectangle' operation is invalid");
    }
    // Match color luma.
    if ($arguments['fill_color'] && $arguments['fill_color_luma']) {
      $arguments['fill_color'] = ColorUtility::matchLuma($arguments['fill_color']);
    }
    if ($arguments['border_color'] && $arguments['border_color_luma']) {
      $arguments['border_color'] = ColorUtility::matchLuma($arguments['border_color']);
    }
    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['fill_color']) {
      $color = $this->allocateColorFromRgba($arguments['fill_color']);
      return imagefilledpolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), 4, $color);
    }
    if ($arguments['border_color']) {
      $color = $this->allocateColorFromRgba($arguments['border_color']);
      return imagepolygon($this->getToolkit()->getResource(), $this->getRectangleCorners($arguments['rectangle']), 4, $color);
    }
  }

}
