<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageCreateTransparent.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\Utility\Color;

/**
 * Defines Textimage GD2 create transparent operation.
 *
 * If file type does not allow transparency, the image will be filled in
 * white. For .gif files, takes transparency from the original image resource
 * if available and no transparent color is specified in input.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_create_transparent",
 *   toolkit = "gd",
 *   operation = "textimage_create_transparent",
 *   label = @Translation("Textimage create transparent image"),
 *   description = @Translation("Create a truecolor transparent image.")
 * )
 */
class TextimageCreateTransparent extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'width' => array(
        'description' => 'The width of the image, in pixels',
      ),
      'height' => array(
        'description' => 'The height of the image, in pixels',
      ),
      'transparent' => array(
        'description' => 'The transparent color array red/blue/green for gif transparency',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $data = array(
      'width' => $arguments['width'],
      'height' => $arguments['height'],
      'mimetype' => $this->getToolkit()->getMimeType(),
    );
    if ($this->getToolkit()->getMimeType() == 'image/gif') {
      if (empty($arguments['transparent'])) {
        // Grab transparent color index from image resource.
        if ($this->getToolkit()->getResource() && $transparent_color = imagecolortransparent($this->getToolkit()->getResource()) >= 0) {
          // The original has a transparent color, allocate to the new image.
          $data['transparent_color'] = Color::rgbToHex(imagecolorsforindex($this->getToolkit()->getResource(), $transparent_color));
        }
        else {
          $data['transparent_color'] = NULL;
        }
      }
      else {
        // Get transparent from the input argument.
        $data['transparent_color'] = Color::rgbToHex($arguments['transparent']);
      }
    }
    return $this->getToolkit()->apply('set_new', $data);
  }

}
