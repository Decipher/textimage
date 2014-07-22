<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDrawPolygon.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

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
      'fill_color' => array(
        'description' => 'The RGBA color of the polygon fill',
        'required' => FALSE,
        'default' => NULL,
      ),
      'fill_color_luma' => array(
        'description' => 'If TRUE, convert RGBA of the polygon fill to best match using luma',
        'required' => FALSE,
        'default' => FALSE,
      ),
      'border_color' => array(
        'description' => 'The RGBA color of the polygon line',
        'required' => FALSE,
        'default' => NULL,
      ),
      'border_color_luma' => array(
        'description' => 'If TRUE, convert RGBA of the polygon line to best match using luma',
        'required' => FALSE,
        'default' => FALSE,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Check color.
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
    $num_points = (int) count($arguments['points']) / 2;
    if ($arguments['fill_color']) {
      $color = $this->getImageColor($arguments['fill_color']);
      return imagefilledpolygon($this->getToolkit()->getResource(), $arguments['points'], $num_points, $color);
    }
    if ($arguments['border_color']) {
      $color = $this->getImageColor($arguments['border_color']);
      return imagepolygon($this->getToolkit()->getResource(), $arguments['points'], $num_points, $color);
    }
  }

}
