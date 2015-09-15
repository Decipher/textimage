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
    $original_res = $this->getToolkit()->getResource();
    $data = array(
      'width' => $targetsize['width'],
      'height' => $targetsize['height'],
      'extension' => image_type_to_extension($this->getToolkit()->getType(), FALSE),
      'transparent_color' => $this->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,  // @todo needs core's #2531678
    );
    $this->getToolkit()->apply('create_new', $data);
    $data = array(
      'rectangle' => new Rectangle($targetsize['width'], $targetsize['height']),
      'fill_color' => $arguments['background_color'],
    );
    $this->getToolkit()->apply('textimage_draw_rectangle', $data);

    // Overlay the current image on the canvas.
    $x = $this->keywordFilter($targetsize['left'], $this->getToolkit()->getWidth(), imagesx($original_res));
    $y = $this->keywordFilter($targetsize['top'], $this->getToolkit()->getHeight(), imagesy($original_res));
    imagealphablending($original_res, TRUE);
    imagesavealpha($original_res, TRUE);
    imagealphablending($this->getToolkit()->getResource(), TRUE);
    imagesavealpha($this->getToolkit()->getResource(), TRUE);
    if (imagecopy($this->getToolkit()->getResource(), $original_res, $x, $y, 0, 0, imagesx($original_res), imagesy($original_res))) {
      imagedestroy($original_res);
      return TRUE;
    }
    return FALSE;
  }

}
