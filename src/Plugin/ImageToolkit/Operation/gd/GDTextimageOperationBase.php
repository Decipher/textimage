<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\GDTextimageOperationBase.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\textimage\Component\ColorUtility;
use Drupal\textimage\Component\Rectangle;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;

/**
 * Base class for Textimage GD image toolkit operations.
 */
abstract class GDTextimageOperationBase extends GDImageToolkitOperationBase {

  use TextimageOperationTrait;

  /**
   * Return the width of a text using TrueType fonts.
   *
   * @param string $text
   *   A text string.
   * @param string $font_size
   *   The font size.
   * @param string $font_uri
   *   The font URI.
   *
   * @return int
   *   The width of the text in pixels.
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
   *
   * @param string $font_size
   *   The font size.
   * @param string $font_uri
   *   The font URI.
   *
   * @return array
   *   An associative array with the following keys:
   *   - 'height' the text height in pixels.
   *   - 'basepoint' an array of x, y coordinates of the font's basepoint.
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
   * Allocates a GD color from an RGBA hexadecimal.
   *
   * @param string $rgba_hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return int
   *   A GD color index.
   */
  protected function allocateColorFromRgba($rgba_hex) {
    list($r, $g, $b, $alpha) = array_values($this->hexToRgba($rgba_hex));
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
   * @param string $rgba_hex
   *   A string specifing an RGBA color in the format '#RRGGBBAA'.
   *
   * @return array
   *   An array with four elements for red, green, blue, and alpha.
   */
  public function hexToRgba($rgba_hex) {
    $rgbHex = Unicode::substr($rgba_hex, 0, 7);
    try {
      $rgb = Color::hexToRgb($rgbHex);
      $opacity = ColorUtility::rgbaToOpacity($rgba_hex);
      $alpha = 127 - floor(($opacity / 100) * 127);
      $rgb['alpha'] = $alpha;
      return $rgb;
    }
    catch (\InvalidArgumentException $e) {
      return FALSE;
    }
  }

  /**
   * Convert a rectangle to a sequence of point coordinates.
   *
   * GD requires a simple array of point coordinates in its
   * imagepolygon() function.
   *
   * @param \Drupal\textimage\Component\Rectangle $rect
   *   A Rectangle object.
   *
   * @return array
   *   A simple array of 8 point coordinates.
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
