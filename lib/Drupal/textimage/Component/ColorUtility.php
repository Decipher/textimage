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
    list($r, $g, $b, $alpha) = array_values(imagecache_actions_hex2rgba($rgba));
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

}
