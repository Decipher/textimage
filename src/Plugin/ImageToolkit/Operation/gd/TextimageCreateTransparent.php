<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageCreateTransparent.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

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
    $res = imagecreatetruecolor($arguments['width'], $arguments['height']);
    if ($this->getToolkit()->getMimeType() == 'image/png') {
      imagealphablending($res, FALSE);
      $transparency = imagecolorallocatealpha($res, 0, 0, 0, 127);
      imagefill($res, 0, 0, $transparency);
      imagealphablending($res, TRUE);
      imagesavealpha($res, TRUE);
    }
    elseif ($this->getToolkit()->getMimeType() == 'image/gif') {
      if (empty($transparent)) {
        // Grab transparent color index from image resource.
        if ($this->getToolkit()->getResource() && $transparent = imagecolortransparent($this->getToolkit()->getResource()) >= 0) {
          // The original has a transparent color, allocate to the new image.
          $transparent_color = imagecolorsforindex($this->getToolkit()->getResource(), $transparent);
          $transparent = imagecolorallocate($res, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
        }
        else {
          // No incoming image or no transparency channel, no color specified,
          // fill white.
          $transparent = imagecolorallocate($res, 255, 255, 255);
        }
      }
      else {
        // Get transparent from the input argument.
        $transparent = imagecolorallocate($res, $transparent['red'], $transparent['green'], $transparent['blue']);
      }
      // Flood with our transparent color.
      if ($transparent >= 0) {
        imagefill($res, 0, 0, $transparent);
        imagecolortransparent($res, $transparent);
      }
    }
    else {
      imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
    }

    $this->getToolkit()->setResource($res);
    return TRUE;
  }

}
