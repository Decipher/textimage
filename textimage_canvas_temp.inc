<?php

use Drupal\textimage\Component\ColorUtility;

/**
 * Places one image over another.
 *
 * @param stdClass $image
 *   An image object.
 * @param stdClass  $layer
 *   Image object to be placed over or under the $image image.
 * @param int $x
 *   Position of the overlay.
 * @param int $y
 *   Position of the overlay.
 * @param int $alpha
 *   Transparency of the overlay from 0-100. 0 is totally transparent. 100
 *   (default) is totally opaque.
 * @param boolean $reverse
 *   Flag to indicate the 'overlay' actually goes under the image.
 *
 * @return boolean
 *   true on success, false otherwise.
 */
function image_overlay($image, $layer, $x, $y, $alpha = 100, $reverse = FALSE) {
  if ($reverse) {
    $x = imagecache_actions_keyword_filter($x, $layer->getWidth(), $image->getWidth());
    $y = imagecache_actions_keyword_filter($y, $layer->getHeight(), $image->getHeight());
  }
  else {
    $x = imagecache_actions_keyword_filter($x, $image->getWidth(), $layer->getWidth());
    $y = imagecache_actions_keyword_filter($y, $image->getHeight(), $layer->getHeight());
  }
  return _textimage_toolkit_invoke('overlay', $image, array($layer, $x, $y, $alpha, $reverse));
}

/**
 * GD toolkit specific implementation of the image overlay effect.
 *
 * NOTE that the PHP libraries are not great at merging images SO we include a
 * library that does it pixel-by-pixel which is INCREDIBLY inefficient. If this
 * can be improved, in a way that supports all transparency, please let us know!
 *
 * A watermark is layer onto image, return the image. An underlay is image onto
 * layer, return the layer. Almost identical, but seeing as we work with
 * resource handles, the handle needs to be swapped before returning.
 *
 * @param stdClass $image
 *   An image object.
 * @param stdClass $layer
 *   Image object to be placed over or under the $image image.
 * @param int $x
 *   Position of the overlay.
 * @param int $y
 *   Position of the overlay.
 * @param int $alpha
 *   Transparency of the overlay from 0-100. 0 is totally transparent. 100
 *   (default) is totally opaque.
 * @param boolean $reverse
 *   Flag to indicate that the 'overlay' actually goes under the image.
 *
 * @return boolean
 *   true on success, false otherwise.
 */
function image_gd_overlay($image, $layer, $x, $y, $alpha = 100, $reverse = FALSE) {
  // If the given alpha is 100%, we can use imagecopy - which actually works,
  // is more efficient, and seems to retain the overlays partial transparency.
  // Still does not work great for indexed gifs though?
  if ($reverse) {
    $upper = &$image;
    $lower = &$layer;
  }
  else {
    $upper = &$layer;
    $lower = &$image;
  }
  if ($alpha == 100 && ($upper->getMimeType() != 'image/gif')) {
    imagealphablending($lower->getToolkit()->getResource(), TRUE);
    imagesavealpha($lower->getToolkit()->getResource(), TRUE);
    imagealphablending($upper->getToolkit()->getResource(), TRUE);
    imagesavealpha($upper->getToolkit()->getResource(), TRUE);
    imagecopy($lower->getToolkit()->getResource(), $upper->getToolkit()->getResource(), $x, $y, 0, 0, $upper->getWidth(), $upper->getHeight());
    imagedestroy($upper->getToolkit()->getResource());
    $image->getToolkit()->setResource($lower->getToolkit()->getResource());
  }
/*/  else {
    // imagecopy() cannot be used and we have to use the slow library.
    module_load_include('inc', 'imagecache_actions', 'watermark');
    $watermark = new watermark();
    $result_img = $watermark->create_watermark($lower->getToolkit()->getResource(), $upper->getToolkit()->getResource(), $x, $y, $alpha);
    // Watermark creates a new image resource, so clean up both old images.
    imagedestroy($lower->getToolkit()->getResource());
    imagedestroy($upper->getToolkit()->getResource());
    $image->getToolkit()->setResource($result_img);
  }*/

  return TRUE;
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
function imagecache_actions_keyword_filter($value, $base_size, $layer_size) {
  // See above for the patterns this matches
  if (! preg_match('/([a-z]*)([\+\-]?)(\d*)([^\d]*)/', $value, $results) ) {
    trigger_error("imagecache_actions had difficulty parsing the string '$value' when calculating position. Please check the syntax.", E_USER_WARNING);
  }
  list(, $keyword, $plusminus, $value, $unit) = $results;

  return imagecache_actions_calculate_offset($keyword, $plusminus . $value . $unit, $base_size, $layer_size);
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
function imagecache_actions_calculate_offset($keyword, $value, $base_size, $layer_size) {
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
function imagecache_actions_percent_filter($length_specification, $current_length) {
  if (strpos($length_specification, '%') !== FALSE) {
    $length_specification =  $current_length !== NULL ? str_replace('%', '', $length_specification) * 0.01 * $current_length : NULL;
  }
  return $length_specification;
}
