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
        'description' => 'Image object to be placed over or under the current image.',
      ),
      'layer_on_top' => array(
        'description' => 'Flag to indicate if the layer goes on top of the current image.',
        'required' => FALSE,
        'default' => TRUE,
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
    if ($arguments['layer_on_top']) {
      $arguments['x'] = $this->keywordFilter($arguments['x'], $this->getToolkit()->getWidth(), $arguments['layer']->getWidth());
      $arguments['y'] = $this->keywordFilter($arguments['y'], $this->getToolkit()->getHeight(), $arguments['layer']->getHeight());
    }
    else {
      $arguments['x'] = $this->keywordFilter($arguments['x'], $arguments['layer']->getWidth(), $this->getToolkit()->getWidth());
      $arguments['y'] = $this->keywordFilter($arguments['y'], $arguments['layer']->getHeight(), $this->getToolkit()->getHeight());
    }

    return $arguments;
  }

}
