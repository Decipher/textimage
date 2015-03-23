<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageSetGifTransparentColor.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\SafeMarkup;

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
class TextimageSetGifTransparentColor extends GDTextimageOperationBase  {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'transparent_color' => array(
        'description' => 'The RGB hex color for GIF transparency',
        'required' => FALSE,
        'default' => '#ffffff',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Assure transparent color is a valid hex string.
    if ($arguments['transparent_color'] && !Color::validateHex($arguments['transparent_color'])) {
      throw new \InvalidArgumentException(SafeMarkup::format("Invalid transparent color (@value) specified for the image 'textimage_set_gif_transparent_color' operation", array('@value' => $arguments['transparent_color'])));
    }

    return $arguments;
  }

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
