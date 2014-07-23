<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageSetTransparency.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

/**
 * Defines Textimage GD2 set transparency operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_set_transparency",
 *   toolkit = "gd",
 *   operation = "textimage_set_transparency",
 *   label = @Translation("Textimage set transparency"),
 *   description = @Translation("Set a color to transparent, mainly to support gif files transparency.")
 * )
 */
class TextimageSetTransparency extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'color' => array(
        'description' => 'The transparent color array red/blue/green for gif transparency',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($arguments['color']) {
      list($r, $g, $b) = array_values(ColorUtility::hexToRgba($arguments['color']));
      $transparent = imagecolorallocate($this->getToolkit()->getResource(), $r, $g, $b);
      imagecolortransparent($this->getToolkit()->getResource(), $transparent);
    }
  }

}
