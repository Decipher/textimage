<?php
/**
 * @file
 * GD2 toolkit for image manipulation within Textimage.
 */

use Drupal\Core\Image\ImageInterface;
use Drupal\textimage\Component\BoundingBox;
use Drupal\textimage\Component\ColorUtility;
use Drupal\textimage\Plugin\ImageEffect\TextimageText;
use Drupal\textimage\Plugin\ImageToolkit\Operation\gd\GDTextimageOperationBase;

/**
 * Return the bounding box of a text using TrueType fonts.
 */
function image_gd_textimage_get_bounding_box($image, $data = array()) {

  $box = new BoundingBox();

  $fontfile = GDTextimageOperationBase::getFontPath($data['fontfile']);

  // Need to calculate the height independently from primitive as
  // lack of descending/ascending characters will limit the height.
  // So to have uniformity we take a dummy string with ascending and
  // descending characters to set to max height possible.
  $box->set('points', imagettfbbox($data['size'], 0, $fontfile, 'bdfhkltgjpqyBDFHKLTGJPQY§@çÅÀÈÉÌÒÇ'));
  $height = $data['lines'] * $box->get('height');

  // Now get the box for full text to get width.
  $box->set('points', imagettfbbox($data['size'], 0, $fontfile, $data['text']));

  // Reset height.
  $box->set('height', $height);

  // Rotate if angle specified.
  if ($data['angle']) {
    $box = $box->getTranslatedBox($data['angle']);
  }

  return $box;

}

/**
 * Writes the outline/shadow of a given text into the image.
 *
 * Credit to John Ciacia.
 *
 * @param object $image
 *   Image
 * @param array $data
 *   Effect data, an array:
 *    size - font size
 *    angle - angle in degrees to rotate the text
 *    fontfile - file path of TrueType font to use
 *    text - The text string in UTF-8 encoding
 *    x - Upper left corner of the text
 *    y - Lower left corner of the text
 *    strokecolor - the rgba color of the text border
 *    top, right, bottom, left - number of pixels of the text border
 *      on each side of the text
 *
 * @see http://www.johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
 */
function image_gd_textimage_text_stroke($image, $data = array()) {
  $fontfile = GDTextimageOperationBase::getFontPath($data['fontfile']);
  for ($c1 = ($data['x'] - abs($data['left'])); $c1 <= ($data['x'] + abs($data['right'])); $c1++) {
    for ($c2 = ($data['y'] - abs($data['top'])); $c2 <= ($data['y'] + abs($data['bottom'])); $c2++) {
      $bg = imagettftext(
        $image->getToolkit()->getResource(),
        $data['size'],
        $data['angle'],
        $c1,
        $c2,
        $data['strokecolor'],
        $fontfile,
        $data['text']
      );
      if ($bg == FALSE) {
        return FALSE;
      }
    }
  }
  return TRUE;
}

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
