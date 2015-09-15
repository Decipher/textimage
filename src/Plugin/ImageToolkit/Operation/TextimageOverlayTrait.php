<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOverlayTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

/**
 * Base trait for Textimage overlay operations.
 */
trait TextimageOverlayTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'layer' => array(
        'description' => 'Image object to be placed over the current image.',
      ),
      'x' => array(
        'description' => 'x-position of the overlay.',
      ),
      'y' => array(
        'description' => 'y-position of the overlay.',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $arguments['x'] = $this->keywordFilter($arguments['x'], $this->getToolkit()->getWidth(), $arguments['layer']->getWidth());
    $arguments['y'] = $this->keywordFilter($arguments['y'], $this->getToolkit()->getHeight(), $arguments['layer']->getHeight());
    return $arguments;
  }

}
