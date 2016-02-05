<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDrawEllipse.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\gd\GDOperationTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;

/**
 * Defines Textimage GD2 draw ellipse operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_draw_ellipse",
 *   toolkit = "gd",
 *   operation = "textimage_draw_ellipse",
 *   label = @Translation("Textimage Draw Ellipse"),
 *   description = @Translation("Draws on the image an ellipse of the specified color.")
 * )
 */
class TextimageDrawEllipse extends GDImageToolkitOperationBase {

  use TextimageOperationTrait;
  use GDOperationTrait;

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'cx' => array(
        'description' => 'x-coordinate of the center.',
      ),
      'cy' => array(
        'description' => 'y-coordinate of the center.',
      ),
      'width' => array(
        'description' => 'The ellipse width.',
      ),
      'height' => array(
        'description' => 'The ellipse height.',
      ),
      'color' => array(
        'description' => 'The fill color, in RGBA format.',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $color = $this->allocateColorFromRgba($arguments['color']);
    return imagefilledellipse($this->getToolkit()->getResource(), $arguments['cx'], $arguments['cy'], $arguments['width'], $arguments['height'], $color);
  }

}
