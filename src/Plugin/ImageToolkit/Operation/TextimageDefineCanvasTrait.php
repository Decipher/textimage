<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageDefineCanvasTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

/**
 * Base trait for Textimage define canvas operations.
 */
trait TextimageDefineCanvasTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'background_color' => array(
        'description' => 'Color',
        'required' => FALSE,
        'default' => NULL,
      ),
      'exact' => array(
        'description' => 'Exact dimensions canvas',
        'required' => FALSE,
        'default' => NULL,
      ),
      'relative' => array(
        'description' => 'Relative dimensions canvas',
        'required' => FALSE,
        'default' => NULL,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    $targetsize = array();
    // May be given either exact or relative dimensions.
    if ($arguments['exact'] && ($arguments['exact']['width'] || $arguments['exact']['height'])) {
      // Allows only one dimension to be used if the other is unset.
      if (!$arguments['exact']['width']) {
        $arguments['exact']['width'] = $this->getToolkit()->getWidth();
      }
      if (!$arguments['exact']['height']) {
        $arguments['exact']['height'] = $this->getToolkit()->getHeight();
      }

      $targetsize['width'] = $this->percentFilter($arguments['exact']['width'], $this->getToolkit()->getWidth());
      $targetsize['height'] = $this->percentFilter($arguments['exact']['height'], $this->getToolkit()->getHeight());

      $targetsize['left'] = image_filter_keyword($arguments['exact']['xpos'], $targetsize['width'], $this->getToolkit()->getWidth());
      $targetsize['top'] = image_filter_keyword($arguments['exact']['ypos'], $targetsize['height'], $this->getToolkit()->getHeight());

    }
    else {
      // Calculate relative size.
      $targetsize['width'] = $this->getToolkit()->getWidth() + $arguments['relative']['leftdiff'] + $arguments['relative']['rightdiff'];
      $targetsize['height'] = $this->getToolkit()->getHeight() + $arguments['relative']['topdiff'] + $arguments['relative']['bottomdiff'];
      $targetsize['left'] = $arguments['relative']['leftdiff'];
      $targetsize['top'] = $arguments['relative']['topdiff'];
    }

    // All the math is done.
    $arguments['targetsize'] = $targetsize;

    return $arguments;
  }

}
