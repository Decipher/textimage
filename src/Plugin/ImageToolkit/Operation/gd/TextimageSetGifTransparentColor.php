<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageSetGifTransparentColor.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\gd\GDOperationTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageSetGifTransparentColorTrait;

/**
 * Defines GD2 textimage set_gif_transparent_color image operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_set_gif_transparent_color",
 *   toolkit = "gd",
 *   operation = "textimage_set_gif_transparent_color",
 *   label = @Translation("Set the image transparent color"),
 *   description = @Translation("Set the image transparent color for GIF images.")
 * )
 */
class TextimageSetGifTransparentColor extends GDImageToolkitOperationBase {

  use TextimageOperationTrait;
  use GDOperationTrait;
  use TextimageSetGifTransparentColorTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if ($this->getToolkit()->getType() == IMAGETYPE_GIF && $arguments['transparent_color']) {
      $rgb = Color::hexToRgb($arguments['transparent_color']);
      $color = imagecolorallocate($this->getToolkit()->getResource(), $rgb['red'], $rgb['green'], $rgb['blue']);
      imagecolortransparent($this->getToolkit()->getResource(), $color);
    }
    return TRUE;
  }

}
