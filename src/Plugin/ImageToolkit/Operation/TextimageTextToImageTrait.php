<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageTextToImageTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

/**
 * Base trait for Textimage text-to-image operations.
 */
trait TextimageTextToImageTrait {

  /**
   * {@inheritdoc}
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
      'debug_visuals' => [
        'description' => 'Indicates if text bounding boxes need to be visualised. Only used in debugging.',
      ],
    ];
  }

}
