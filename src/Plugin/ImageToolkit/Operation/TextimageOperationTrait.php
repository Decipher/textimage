<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation;

use Drupal\Component\Utility\String;
use Drupal\Core\StreamWrapper\LocalStream;

/**
 * Base trait for Textimage image toolkit operations.
 */
trait TextimageOperationTrait {

  /**
   * An array of resolved font file URIs.
   *
   * @var array
   */
  static $fontPaths = [];

  /**
   * Return the real path of the specified file.
   *
   * @param string $uri
   *   An URI.
   *
   * @return string
   *   The local path of the file.
   */
  protected function getRealPath($uri) {
    $uri_wrapper = file_stream_wrapper_get_instance_by_uri($uri);
    if ($uri_wrapper instanceof LocalStream) {
      return $uri_wrapper->realpath();
    }
    else {
      return is_file($uri) ? $uri : NULL;
    }
  }

  /**
   * Return the path of the font file.
   *
   * @param string $font_uri
   *   The font URI.
   *
   * @return string
   *   The local path of the font file.
   */
  protected function getFontPath($font_uri) {
    if (!$font_uri) {
      throw new \InvalidArgumentException('Textimage - Font file not specified');
    }
    if (!isset(static::$fontPaths[$font_uri])) {
      if (!$ret = $this->getRealPath($font_uri)) {
        throw new \InvalidArgumentException(String::format('Textimage - Could not find the font file @fontfile', array('@fontfile' => $font_uri)));
      }
      static::$fontPaths[$font_uri] = $ret;
    }
    return static::$fontPaths[$font_uri];
  }

  /**
   * Computes a length based on a length specification and an actual length.
   *
   * Examples:
   *  (50, 400) returns 50; (50%, 400) returns 200;
   *  (50, null) returns 50; (50%, null) returns null;
   *  (null, null) returns null; (null, 100) returns null.
   *
   * @param string|null $length_specification
   *   The length specification. An integer constant or a % specification.
   * @param int|null $current_length
   *   The current length. May be null.
   *
   * @return int|null
   */
  protected function percentFilter($length_specification, $current_length) {
    if (strpos($length_specification, '%') !== FALSE) {
      $length_specification =  $current_length !== NULL ? str_replace('%', '', $length_specification) * 0.01 * $current_length : NULL;
    }
    return $length_specification;
  }

  /**
   * Accept a keyword (center, top, left, etc) and return it as an offset in pixels.
   * Called on either the x or y values.
   *
   * May  be something like "20", "center", "left+20", "bottom+10". + values are
   * in from the sides, so bottom+10 is 10 UP from the bottom.
   *
   * "center+50" is also OK.
   *
   * "30%" will place the CENTER of the object at 30% across. to get a 30% margin,
   * use "left+30%"
   *
   * @param string|int $value
   *   string or int value.
   * @param int $base_size
   *   Size in pixels of the range this item is to be placed in.
   * @param int $layer_size
   *   Size in pixels of the object to be placed.
   *
   * @return int
   */
  protected function keywordFilter($value, $base_size, $layer_size) {
    // See above for the patterns this matches
    if (! preg_match('/([a-z]*)([\+\-]?)(\d*)([^\d]*)/', $value, $results) ) {
      // @todo exception
      trigger_error("imagecache_actions had difficulty parsing the string '$value' when calculating position. Please check the syntax.", E_USER_WARNING);
    }
    list(, $keyword, $plusminus, $value, $unit) = $results;

    return $this->calculateOffset($keyword, $plusminus . $value . $unit, $base_size, $layer_size);
  }

  /**
   * Positive numbers are IN from the edge, negative offsets are OUT.
   *
   * $keyword, $value, $base_size, $layer_size
   * eg
   * left,20 200, 100 = 20
   * right,20 200, 100 = 80 (object 100 wide placed 20 px from the right = x=80)
   *
   * top,50%, 200, 100 = 50 (Object is centered when using %)
   * top,20%, 200, 100 = -10
   * bottom,-20, 200, 100 = 220
   * right, -25%, 200, 100 = 200 (this ends up just off screen)
   *
   * Also, the value can be a string, eg "bottom-100", or "center+25%"
   */
  protected function calculateOffset($keyword, $value, $base_size, $layer_size) {
    $offset = 0; // used to account for dimensions of the placed object
    $direction = 1;
    $base = 0;
    if ($keyword == 'right' || $keyword == 'bottom') {
      $direction = -1;
      $offset = -1 * $layer_size;
      $base = $base_size;
    }
    if ($keyword == 'middle' || $keyword == 'center') {
      $base = $base_size / 2;
      $offset = -1 * ($layer_size / 2);
    }

    // Keywords may be used to stand in for numeric values
    switch ($value) {
      case 'left':
      case 'top':
        $value = 0;
        break;
      case 'middle':
      case 'center':
        $value = $base_size / 2;
        break;
      case 'bottom':
      case 'right':
        $value = $base_size;
    }

    // Handle keyword-number cases like top+50% or bottom-100px,
    // @see imagecache_actions_keyword_filter().
    if (preg_match('/^(.+)([\+\-])(\d+)([^\d]*)$/', $value, $results)) {
      list(, $value_key, $value_mod, $mod_value, $mod_unit) = $results;
      if ($mod_unit == '%') {
        $mod_value = $mod_value / 100 * $base_size;
      }
      $mod_direction = ($value_mod == '-') ? -1 : + 1;
      switch ($value_key) {
        case 'left':
        case 'top':
        default:
          $mod_base = 0;
          break;
        case 'middle':
        case 'center':
          $mod_base = $base_size / 2;
          break;
        case 'bottom':
        case 'right':
          $mod_base = $base_size;
          break;
      }
      $modified_value = $mod_base + ($mod_direction * $mod_value);
      return $modified_value;
    }

    // handle % values
    if (substr($value, strlen($value) -1, 1) == '%') {
      $value = intval($value / 100 * $base_size);
      $offset = -1 * ($layer_size / 2);
    }
    $value = $base + ($direction * $value);

    // Add any extra offset to position the item
    return $value + $offset;
  }

}
