<?php

// @todo revise if #2063373 gets in

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageReplaceImage.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

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

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'replacement_image' => array(
        'description' => 'The image to be used to replace current one',
      ),
    );
  }

  // @todo validate arguments replacement image should be a ImageInterface object

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if (!$arguments['replacement_image']->isValid()) {
      $this->logger->error('Invalid image at @source.', array('@source' => $arguments['replacement_image']->getSource()));
      return FALSE;
    }

    imagedestroy($this->getToolkit()->getResource());
    $this->getToolkit()->setResource($arguments['replacement_image']->getToolkit()->getResource());
    $this->getToolkit()->setType($arguments['replacement_image']->getToolkit()->getType());

    return TRUE;
  }

}
