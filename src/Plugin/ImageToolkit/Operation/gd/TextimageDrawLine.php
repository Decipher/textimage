<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDrawLine.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\gd\GDOperationTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;

/**
 * Defines Textimage GD2 draw line operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_draw_line",
 *   toolkit = "gd",
 *   operation = "textimage_draw_line",
 *   label = @Translation("Textimage Draw Line"),
 *   description = @Translation("Draws on the image a line of the specified color.")
 * )
 */
class TextimageDrawLine extends GDImageToolkitOperationBase {

  use TextimageOperationTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'x1' => array(
        'description' => 'x-coordinate for first point.',
      ),
      'y1' => array(
        'description' => 'y-coordinate for first point.',
      ),
      'x2' => array(
        'description' => 'x-coordinate for second point.',
      ),
      'y2' => array(
        'description' => 'y-coordinate for second point.',
      ),
      'color' => array(
        'description' => 'The line color, in RGBA format.',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $color = $this->allocateColorFromRgba($arguments['color']);
    return imageline($this->getToolkit()->getResource(), $arguments['x1'], $arguments['y1'], $arguments['x2'], $arguments['y2'], $color);
  }

}
