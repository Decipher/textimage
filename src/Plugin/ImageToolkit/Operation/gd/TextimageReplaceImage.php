<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageReplaceImage.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageReplaceImageTrait;

/**
 * Defines Textimage GD2 image replace operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_replace_image",
 *   toolkit = "gd",
 *   operation = "textimage_replace_image",
 *   label = @Translation("Textimage Replace Image"),
 *   description = @Translation("Replace the current image with another one.")
 * )
 */
class TextimageReplaceImage extends GDTextimageOperationBase {

  use TextimageReplaceImageTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Prepare the new image.
    $original_res = $this->getToolkit()->getResource();
    $data = [
      'width' => $arguments['replacement_image']->getWidth(),
      'height' => $arguments['replacement_image']->getHeight(),
      'extension' => image_type_to_extension($arguments['replacement_image']->getToolkit()->getType(), FALSE),
      'transparent_color' => $arguments['replacement_image']->getToolkit()->getTransparentColor(),
      'is_temp' => TRUE,  // @todo needs core's #2531678
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      return FALSE;
    }

    // Overlay replacement image.
    $data = [
      'layer' => $arguments['replacement_image'],
      'x' => 0,
      'y' => 0,
    ];
    return $this->getToolkit()->apply('textimage_overlay', $data);
  }

}
