<?php
/**
 * @file
 * GD2 toolkit for image manipulation within Textimage.
 */

use Drupal\Core\Image\ImageInterface;
use Drupal\textimage\Component\ColorUtility;

/**
 * Create a truecolor transparent image.
 *
 * If file type does not allow transparency, the image will be filled in
 * white. For .gif files, takes transparency from the original image resource
 * if available and no transparent color is specified in input.
 *
 * @param $image
 *   An image object.
 * @param $width
 *   The width of the image, in pixels.
 * @param $height
 *   The height of the image, in pixels.
 * @param $tranparent
 *   The transparent color array red/blue/green for gif transparency.
 *
 * @return
 *   A GD image handle.
 */
function image_gd_textimage_create_transparent($image, $width, $height, $transparent = NULL) {
  $res = imagecreatetruecolor($width, $height);
  if ($image->getMimeType() == 'image/png') {
    imagealphablending($res, FALSE);
    $transparency = imagecolorallocatealpha($res, 0, 0, 0, 127);
    imagefill($res, 0, 0, $transparency);
    imagealphablending($res, TRUE);
    imagesavealpha($res, TRUE);
  }
  elseif ($image->getMimeType() == 'image/gif') {
    if (empty($transparent)) {
      // Grab transparent color index from image resource.
      if ($image->getToolkit()->getResource() && $transparent = imagecolortransparent($image->getToolkit()->getResource()) >= 0) {
        // The original has a transparent color, allocate to the new image.
        $transparent_color = imagecolorsforindex($image->getToolkit()->getResource(), $transparent);
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

  // @todo should not create an image here, just changing the resource (see XXXX)
  $new_image = \Drupal::service('image.factory')->get(drupal_get_path('module', 'textimage') . '/misc/images/base.png'); // @todo no longer possible to get dummy images
  //$new_image = clone $image;
  $new_image->getToolkit()->setResource($res);
  return $new_image;
}

/**
 * Set a color to transparent.
 *
 * Mainly to support .gif files transparency.
 *
 * @param $image
 *   An image object.
 * @param $color
 *   The transparent color array red/blue/green for .gif transparency.
 */
function image_gd_textimage_set_transparency($image, $color) {
  if ($color) {
    list($r, $g, $b) = array_values(ColorUtility::hexToRgba($color));
    $transparent = imagecolorallocate($image->getToolkit()->getResource(), $r, $g, $b);
    imagecolortransparent($image->getToolkit()->getResource(), $transparent);
  }
}
