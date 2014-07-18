<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDrawPolygon.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

/**
 * Defines Textimage GD2 draw polygon operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_draw_polygon",
 *   toolkit = "gd",
 *   operation = "textimage_draw_polygon",
 *   label = @Translation("Textimage Draw Polygon"),
 *   description = @Translation("Draws on the image a poligon, optionally filling it in with a specified color.")
 * )
 */
class TextimageDrawPolygon extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'points' => array(
        'description' => 'An array containing the polygon vertices',
      ),
      'num_points' => array(
        'description' => 'Total number of points (vertices)',
      ),
      'fill_color' => array(
        'description' => 'The RGBA color of the polygon fill',
        'required' => FALSE,
        'default' => NULL,
      ),
      'border_color' => array(
        'description' => 'The RGBA color of the polygon line',
        'required' => FALSE,
        'default' => NULL,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['fill_color']) {
      $color = $this->getImageColor($arguments['fill_color']);
      return imagefilledpolygon($this->getToolkit()->getResource(), $arguments['points'], $arguments['num_points'], $color);
    }
    if ($arguments['border_color']) {
      $color = $this->getImageColor($arguments['border_color']);
      return imagepolygon($this->getToolkit()->getResource(), $arguments['points'], $arguments['num_points'], $color);
    }
  }

}
