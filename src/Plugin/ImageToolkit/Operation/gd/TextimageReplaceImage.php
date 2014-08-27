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

    $original_resource = $this->getToolkit()->getResource();

    // For GIF images, keep a record of the transparent color.
    $transparent_gif_color = $this->getTransparentColor($arguments['replacement_image']);

    $this->getToolkit()->apply('textimage_set_new', array(
      'width' => $this->getToolkit()->getWidth(),
      'height' => $this->getToolkit()->getHeight(),
      'mimetype' => $arguments['replacement_image']->getToolkit()->getMimeType(),
      'transparent_color' => $transparent_gif_color,
    ));

    imagecopy($this->getToolkit()->getResource(), $original_resource, 0, 0, 0, 0, imagesx($original_resource), imagesy($original_resource));
    imagedestroy($original_resource);

    return TRUE;
  }

  public function getTransparentColor($image) {
    if (!$image->getToolkit()->getResource() || $image->getToolkit()->getType() != IMAGETYPE_GIF) {
      return NULL;
    }
    // Find out if a transparent color is set, will return -1 if no
    // transparent color has been defined in the image.
    $transparent = imagecolortransparent($image->getToolkit()->getResource());
    if ($transparent >= 0) {
      // Find out the number of colors in the image palette. It will be 0 for
      // truecolor images.
      $palette_size = imagecolorstotal($image->getToolkit()->getResource());
      if ($palette_size == 0 || $transparent < $palette_size) {
        // Return the transparent color, either if it is a truecolor image
        // or if the transparent color is part of the palette.
        // Since the index of the transparent color is a property of the
        // image rather than of the palette, it is possible that an image
        // could be created with this index set outside the palette size.
        $rgb = imagecolorsforindex($image->getToolkit()->getResource(), $transparent);
        unset($rgb['alpha']);
        return Color::rgbToHex($rgb);
      }
      return NULL;
    }
    return NULL;
  }

}
