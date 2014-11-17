<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\GDTextimageOperationBase.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\textimage\Component\ColorUtility;
use Drupal\textimage\Component\Rectangle;

/**
 * Base class for Textimage GD image toolkit operations.
 */
abstract class GDTextimageOperationBase extends GDImageToolkitOperationBase {

  /**
   * Return the path of the font file, in a format usable by GD.
   * @todo
   */
  protected function getFontPath($font_uri) {
    $font_wrapper = file_stream_wrapper_get_instance_by_uri($font_uri);
    if ($font_wrapper instanceof LocalStream) {
      $ret = $font_wrapper->realpath();
    }
    else {
      $ret = is_file($font_uri) ? $font_uri : NULL;
    }
    if (!$ret) {
      $this->logger->error("Textimage could not find the font file @fontfile.", array('@fontfile' => $font_uri));
    }
    return $ret;
  }

  /**
   * Return the width of a text using TrueType fonts.
   * @todo
   */
  public function getTextWidth($text, $font_size, $font_uri) {
    // Get fully qualified font file information.
    if (!$font_file = $this->getFontPath($font_uri)) {
      return NULL;
    }
    // Get the bounding box for $text to get width.
    $points = imagettfbbox($font_size, 0, $font_file, $text);
    // Return bounding box width.
    return (abs($points[4] - $points[6]) + 1);
  }

  /**
   * Return the height and basepoint of a text using TrueType fonts.
   *
   * Need to calculate the height independently from primitive as
   * lack of descending/ascending characters will limit the height.
   * So to have uniformity we take a dummy string with ascending and
   * descending characters to set to max height possible.
   * @todo
   */
  public function getTextHeightInfo($font_size, $font_uri) {
    // Get fully qualified font file information.
    if (!$font_file = $this->getFontPath($font_uri)) {
      return NULL;
    }
    // Get the bounding box for $text to get height.
    $points = imagettfbbox($font_size, 0, $font_file, 'bdfhkltgjpqyBDFHKLTGJPQY§@çÅÀÈÉÌÒÇ');
    $height = (abs($points[5] - $points[1]) + 1);
    return [
      'height' => $height,
      'basepoint' => [$points[6], -$points[7]],
    ];
  }

  /**
   * Gets a GD imagecolor.
   * @todo
   */
  protected function getImageColor($color) {
    list($r, $g, $b, $alpha) = array_values($this->hexToRgba($color));
    return imagecolorallocatealpha($this->getToolkit()->getResource(), $r, $g, $b, $alpha);
  }

  /**
   * Convert a RGBA hex to its RGBA integer GD components.
   *
   * GD expects a value between 0 and 127 for alpha, where 0 indicates
   * completely opaque while 127 indicates completely transparent.
   * RGBA hexadecimal notation has #00 for transparent and #FF for
   * fully opaque.
   *
   * @param string $hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return array
   *   An array with four elements for red, green, blue, and alpha.
   */
  public function hexToRgba($hex) {
    $rgbHex = Unicode::substr($hex, 0, 7);
    try {
      $rgb = Color::hexToRgb($rgbHex);
      $opacity = ColorUtility::rgbaToOpacity($hex);
      $alpha = 127 - floor(($opacity / 100) * 127);
      $rgb['alpha'] = $alpha;
      return $rgb;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * @todo
   */
  public function getRectangleCorners(Rectangle $rect) {
    $points = [];
    foreach (array('c_d', 'c_c', 'c_b', 'c_a') as $c) {
      $point = $rect->getPoint($c);
      $points[] = $point[0];
      $points[] = $point[1];
    }
    return $points;
  }

}
