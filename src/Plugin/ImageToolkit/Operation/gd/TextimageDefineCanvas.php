<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDefineCanvas.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

/**
 * Defines Textimage GD2 define canvas operation.
 *
 * @todo temp while imagecache_action develops
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_define_canvas",
 *   toolkit = "gd",
 *   operation = "textimage_define_canvas",
 *   label = @Translation("Textimage define canvas image"),
 *   description = @Translation("Defines a canvas for the image.")
 * )
 */
class TextimageDefineCanvas extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'RGB' => array(
        'description' => 'Color',
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

    // Convert from hex (as it is stored in the UI).
    if ($arguments['RGB']['HEX'] && $deduced = ColorUtility::hexToRgba($arguments['RGB']['HEX'])) {
      $arguments['RGB'] = array_merge($arguments['RGB'], $deduced);
    }

    // All the math is done, now defer to the toolkit in use.
    $arguments['targetsize'] = $targetsize;

    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $targetsize = $arguments['targetsize'];
    $RGB = $arguments['RGB'];

    $canvas_image = \Drupal::service('image.factory')->get(drupal_get_path('module', 'textimage') . '/misc/images/base.png'); // @todo no longer possible to get dummy images
    $data = array(
      'width' => $targetsize['width'],
      'height' => $targetsize['height'],
      'mimetype' => $this->getToolkit()->getMimeType(),
    );
    $canvas_image->apply('set_new', $data);
    if ($RGB['HEX']) {
      // Set color, allow it to define transparency, or assume opaque.
      $background = imagecolorallocatealpha($canvas_image->getToolkit()->getResource(), $RGB['red'], $RGB['green'], $RGB['blue'], $RGB['alpha']);
    }
    else {
      // No color, attempt transparency, assume white.
      $background = imagecolorallocatealpha($canvas_image->getToolkit()->getResource(), 255, 255, 255, 127);
    }
    imagefilledrectangle($canvas_image->getToolkit()->getResource(), 0, 0, $targetsize['width'], $targetsize['height'], $background);

    $overlay_data = array(
      'layer' => $canvas_image,
      'layer_on_top' => FALSE,
      'x' => $targetsize['left'],
      'y' => $targetsize['top'],
    );
    $this->getToolkit()->apply('textimage_overlay', $overlay_data);

    return TRUE;
  }

  /**
   * Computes a length based on a length specification and an actual length.
   *
   * Examples:
   *  (50, 400) returns 50; (50%, 400) returns 200;
   *  (50, null) returns 50; (50%, null) returns null;
   *  (null, null) returns null; (null, 100) returns null.
   *
   * @param string|null $length_specification
   *   The length specification. An integer constant or a % specification.
   * @param int|null $current_length
   *   The current length. May be null.
   *
   * @return int|null
   */
  protected function percentFilter($length_specification, $current_length) {
    if (strpos($length_specification, '%') !== FALSE) {
      $length_specification =  $current_length !== NULL ? str_replace('%', '', $length_specification) * 0.01 * $current_length : NULL;
    }
    return $length_specification;
  }

}
