<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageSetGifTransparentColorTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

use Drupal\Component\Utility\Color;

/**
 * Base trait for Textimage set_gif_transparent_color image operation.
 */
trait TextimageSetGifTransparentColorTrait {

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
      $transparent_color = $arguments['transparent_color'];
      throw new \InvalidArgumentException("Invalid transparent color ({$transparent_color}) specified for the image 'textimage_set_gif_transparent_color' operation");
    }

    return $arguments;
  }

}
