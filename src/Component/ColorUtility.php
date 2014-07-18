<?php

/**
 * @file
 * Contains \Drupal\textimage\Component\ColorUtility.
 */

namespace Drupal\textimage\Component;

use Drupal\Component\Utility\Unicode;

/**
 * Textimage - Color handling methods.
 */
abstract class ColorUtility {

  /**
   * Converts an RGB triplet to a hex color.
   *
   * Copy of Color module's _color_pack() function to reduce dependencies.
   */
  public static function pack($rgb, $normalize = FALSE) {
    $out = 0;
    foreach ($rgb as $k => $v) {
      $out |= (($v * ($normalize ? 255 : 1)) << (16 - $k * 8));
    }

    return '#' . str_pad(dechex($out), 6, 0, STR_PAD_LEFT);
  }

  /**
   * Determine best match to over/underlay a defined color.
   *
   * Calculates UCCIR 601 luma of the entered color and returns a black or
   * white color to ensure readibility.
   *
   * @see http://en.wikipedia.org/wiki/Luma_video
   */
  public static function matchLuma($rgba, $soft = FALSE) {
    list($r, $g, $b, $alpha) = array_values(static::hexToRgba($rgba));
    $luma = 1 - (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    if ($luma < 0.5) {
      // Bright colors - black.
      $d = 0;
    }
    else {
      // Dark colors - white.
      $d = 255;
    }
    return static::pack(array($d, $d, $d));
  }

  /**
   * Convert RGBA alpha to percent opacity.
   *
   * @param string $rgba
   *   RGBA hex.
   *
   * @return int
   *   Opacity as percentage (0 = transparent, 100 = fully opaque).
   */
  public static function rgbaToOpacity($rgba) {
    $hex = Unicode::substr($rgba, 7, 2);
    return $hex ? floor(-(hexdec($hex) - 127) / 127 * 100) : 100;
  }

  /**
   * Convert percent opacity to hex alpha.
   *
   * @param int $value
   *   Opacity as percentage (0 = transparent, 100 = fully opaque).
   *
   * @return string
   *   Opacity as HEX.
   */
  public static function opacityToAlpha($value) {
    return ($value == NULL) ? NULL : Unicode::strtoupper(str_pad(dechex(-($value - 100) / 100 * 127), 2, "0", STR_PAD_LEFT));
  }

  /**
   * Convert a hex string to its RGBA (Red, Green, Blue, Alpha) integer
   * components.
   *
   * Stolen from imageapi D6 2011-01
   *
   * @param string $hex
   *   A string specifing an RGB color in the formats:
   *   '#ABC','ABC','#ABCD','ABCD','#AABBCC','AABBCC','#AABBCCDD','AABBCCDD'
   *
   * @return array
   *   An array with four elements for red, green, blue, and alpha.
   *
   * @todo taken from imagecache_actions D7, may be dropped if that becomes a dependency in Textimage D8.
   */
  public static function hexToRgba($hex) {
    $hex = ltrim($hex, '#');
    if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
      // 'FA3' is the same as 'FFAA33' so r=FF, g=AA, b=33
      $r = str_repeat($hex{0}, 2);
      $g = str_repeat($hex{1}, 2);
      $b = str_repeat($hex{2}, 2);
      $a = '0';
    }
    elseif (preg_match('/^[0-9a-f]{6}$/i', $hex)) {
      // #FFAA33 or r=FF, g=AA, b=33
      list($r, $g, $b) = str_split($hex, 2);
      $a = '0';
    }
    elseif (preg_match('/^[0-9a-f]{8}$/i', $hex)) {
      // #FFAA33 or r=FF, g=AA, b=33
      list($r, $g, $b, $a) = str_split($hex, 2);
    }
    elseif (preg_match('/^[0-9a-f]{4}$/i', $hex)) {
      // 'FA37' is the same as 'FFAA3377' so r=FF, g=AA, b=33, a=77
      $r = str_repeat($hex{0}, 2);
      $g = str_repeat($hex{1}, 2);
      $b = str_repeat($hex{2}, 2);
      $a = str_repeat($hex{3}, 2);
    }
    else {
      // error: invalid hex string, @todo: throw exception?
      return FALSE;
    }

    $r = hexdec($r);
    $g = hexdec($g);
    $b = hexdec($b);
    $a = hexdec($a);
    // alpha over 127 is illegal. assume they meant half that.
    if ($a > 127) {
      $a = (int) $a/2;
    }
    return array('red' => $r, 'green' => $g, 'blue' => $b, 'alpha' => $a);
  }

}
