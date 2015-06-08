<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageTextToWrapperTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

/**
 * Base trait for Textimage text-to-wrapper operations.
 */
trait TextimageTextToWrapperTrait {

  /**
   * @todo
   */
  protected function arguments() {
    return [
      'font' => [
        'description' => 'Font metadata.',
      ],
      'layout' => [
        'description' => 'Layout metadata.',
      ],
      'text' => [
        'description' => 'Text metadata.',
      ],
      'text_string' => [
        'description' => 'Actual text string to be placed on the image.',
      ],
      'canvas_width' => [
        'description' => 'Width of the underlying image.',
      ],
      'canvas_height' => [
        'description' => 'Height of the underlying image.',
      ],
      'debug_visuals' => [
        'description' => 'Indicates if text bounding boxes need to be visualised. Only used in debugging.',
      ],
    ];
  }
}
