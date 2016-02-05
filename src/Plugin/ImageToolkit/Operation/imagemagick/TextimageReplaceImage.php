<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\TextimageReplaceImage.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickOperationTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageReplaceImageTrait;

/**
 * Defines Textimage Imagemagick image replace operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_imagemagick_textimage_replace_image",
 *   toolkit = "imagemagick",
 *   operation = "textimage_replace_image",
 *   label = @Translation("Textimage Replace Image"),
 *   description = @Translation("Replace the current image with another one.")
 * )
 */
class TextimageReplaceImage extends ImagemagickImageToolkitOperationBase {

  use ImagemagickOperationTrait;
  use TextimageOperationTrait;
  use TextimageReplaceImageTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $replacement = $arguments['replacement_image'];
    $this->getToolkit()
      ->resetArguments()
      ->setSourceLocalPath($replacement->getToolkit()->getSourceLocalPath())
      ->setSourceFormat($replacement->getToolkit()->getSourceFormat())
      ->setExifOrientation($replacement->getToolkit()->getExifOrientation())
      ->setWidth($replacement->getWidth())
      ->setHeight($replacement->getHeight());
    return TRUE;
  }

}
