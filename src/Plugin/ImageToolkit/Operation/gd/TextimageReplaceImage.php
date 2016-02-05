<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageReplaceImage.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageReplaceImageTrait;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\gd\GDOperationTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;

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
class TextimageReplaceImage extends GDImageToolkitOperationBase {

  use TextimageOperationTrait;
  use GDOperationTrait;
  use TextimageReplaceImageTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Prepare the new image.
    $data = [
      'width' => $arguments['replacement_image']->getWidth(),
      'height' => $arguments['replacement_image']->getHeight(),
      'extension' => image_type_to_extension($arguments['replacement_image']->getToolkit()->getType(), FALSE),
      'transparent_color' => $arguments['replacement_image']->getToolkit()->getTransparentColor(),
      'is_temp' => FALSE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      return FALSE;
    }

    // Overlay replacement image.
    $data = [
      'watermark_image' => $arguments['replacement_image'],
      'x_offset' => 0,
      'y_offset' => 0,
    ];
    return $this->getToolkit()->apply('watermark', $data);
  }

}
