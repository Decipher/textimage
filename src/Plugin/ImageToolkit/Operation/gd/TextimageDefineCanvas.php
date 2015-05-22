<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageDefineCanvas.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageDefineCanvasTrait;
use Drupal\textimage\Component\Rectangle;

/**
 * Defines Textimage GD2 define canvas operation.
 *
 * Much of the code in this class is taken from the imagecache_action module
 * for Drupal 7.
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

  use TextimageDefineCanvasTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $targetsize = $arguments['targetsize'];

    // Prepare the canvas.
    $canvas_image = \Drupal::service('image.factory')->get();
    $data = array(
      'width' => $targetsize['width'],
      'height' => $targetsize['height'],
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE), // @todo double check is this correct - if canvass is below ok, if above then it could be png
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
    );
    $canvas_image->apply('create_new', $data);
    $data = array(
      'rectangle' => new Rectangle($targetsize['width'], $targetsize['height']),
      'fill_color' => $arguments['background_color'],
    );
    $canvas_image->apply('textimage_draw_rectangle', $data);

    // Overlay the current image on the canvas.
    return $this->getToolkit()->apply('textimage_overlay', array('layer' => $canvas_image, 'layer_on_top' => FALSE, 'x' => $targetsize['left'], 'y' => $targetsize['top']));
  }

}
