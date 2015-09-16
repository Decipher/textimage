<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\TextimageTextToWrapper.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageTextToWrapperTrait;

/**
 * Defines Textimage Imagemagick text-to-image operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_imagemagick_textimage_text_to_wrapper",
 *   toolkit = "imagemagick",
 *   operation = "textimage_text_to_wrapper",
 *   label = @Translation("Overlays text over an image"),
 *   description = @Translation("Overlays text over an image.")
 * )
 */
class TextimageTextToWrapper extends ImagemagickTextimageOperationBase {

  use TextimageTextToWrapperTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Get a temporary wrapper image object via the GD toolkit.
    $gd_wrapper = \Drupal::service('image.factory')->get(NULL, 'gd');
    $gd_wrapper->apply('textimage_text_to_wrapper', $arguments);
    // Flush the temporary wrapper to disk, reopen via ImageMagick and return.
    if ($gd_wrapper) {
      $tmp_file = \Drupal::service('file_system')->tempnam('temporary://', 'textimage_');
      $gd_wrapper_destination = $tmp_file . '.png';
      file_unmanaged_move($tmp_file, $gd_wrapper_destination, FILE_CREATE_DIRECTORY);
      $gd_wrapper->save($gd_wrapper_destination);
      $tmp_wrapper = \Drupal::service('image.factory')->get($gd_wrapper_destination, 'imagemagick');
      return $this->getToolkit()->apply('textimage_replace_image', ['replacement_image' => $tmp_wrapper]);
    }
    return FALSE;
  }

}
