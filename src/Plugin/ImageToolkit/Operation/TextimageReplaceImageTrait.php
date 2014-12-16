<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageReplaceImageTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

use Drupal\Component\Utility\String;
use Drupal\Core\Image\ImageInterface;

/**
 * Base trait for Textimage define canvas operations.
 */
trait TextimageReplaceImageTrait {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'replacement_image' => array(
        'description' => 'The image to be used to replace current one.',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    // Ensure replacement_image is an expected ImageInterface object.
    if (!$arguments['replacement_image'] instanceof ImageInterface) {
      throw new \InvalidArgumentException(String::format("Replacement image passed to the 'textimage_replace_image' operation is invalid"));
    }
    // Ensure replacement_image is a valid image.
    if (!$arguments['replacement_image']->isValid()) {
      throw new \InvalidArgumentException(String::format('Invalid image at @source.', array('@source' => $arguments['replacement_image']->getSource())));
    }
    return $arguments;
  }

}
