<?php

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
 */
function imagecache_actions_hex2rgba($hex) {
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
    // error: invalid hex string, @todo: set form error.
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

/**
 * Image effect callback for the define canvas effect.
 *
 * @param stdClass $image
 * @param array $data
 *
 * @return boolean
 *   true on success, false otherwise.
 */
function canvasactions_definecanvas_effect($image, array $data) {
  // May be given either exact or relative dimensions.
  if ($data['exact']['width'] || $data['exact']['height']) {
    // Allows only one dimension to be used if the other is unset.
    if (!$data['exact']['width']) {
      $data['exact']['width'] = $image->getWidth();
    }
    if (!$data['exact']['height']) {
      $data['exact']['height'] = $image->getHeight();
    }

    $targetsize['width'] = imagecache_actions_percent_filter($data['exact']['width'], $image->getWidth());
    $targetsize['height'] = imagecache_actions_percent_filter($data['exact']['height'], $image->getHeight());

    $targetsize['left'] = image_filter_keyword($data['exact']['xpos'], $targetsize['width'], $image->getWidth());
    $targetsize['top'] = image_filter_keyword($data['exact']['ypos'], $targetsize['height'], $image->getHeight());

  }
  else {
    // Calculate relative size.
    $targetsize['width'] = $image->getWidth() + $data['relative']['leftdiff'] + $data['relative']['rightdiff'];
    $targetsize['height'] = $image->getHeight() + $data['relative']['topdiff'] + $data['relative']['bottomdiff'];
    $targetsize['left'] = $data['relative']['leftdiff'];
    $targetsize['top'] = $data['relative']['topdiff'];
  }

  // Convert from hex (as it is stored in the UI).
  if ($data['RGB']['HEX'] && $deduced = imagecache_actions_hex2rgba($data['RGB']['HEX'])) {
    $data['RGB'] = array_merge($data['RGB'], $deduced);
  }

  // All the math is done, now defer to the toolkit in use.
  $data['targetsize'] = $targetsize;

  return _textimage_toolkit_invoke('definecanvas', $image, array($data));
}

/**
 * GD toolkit specific implementation of the define canvas effect.
 *
 * @param stdClass $image
 * @param array $data
 *   The parameters for this effect. $data['targetsize'] is an array expected to
 *   contain a width, height and a left, top.
 *
 * @return bool
 *   true on success, false otherwise.
 */
function image_gd_definecanvas($image, array $data = array()) {

  $targetsize = $data['targetsize'];
  $RGB = $data['RGB'];

  $newcanvas = imagecreatetruecolor($targetsize['width'], $targetsize['height']);
  imagesavealpha($newcanvas, TRUE);
  imagealphablending($newcanvas, FALSE);
  imagesavealpha($image->getToolkit()->getResource(), TRUE);
  if ($RGB['HEX']) {
    // Set color, allow it to define transparency, or assume opaque.
    $background = imagecolorallocatealpha($newcanvas, $RGB['red'], $RGB['green'], $RGB['blue'], $RGB['alpha']);
  }
  else {
    // No color, attempt transparency, assume white.
    $background = imagecolorallocatealpha($newcanvas, 255, 255, 255, 127);
  }
  imagefilledrectangle($newcanvas, 0, 0, $targetsize['width'], $targetsize['height'], $background);

  if ($data['under']) {
    $canvas_object = \Drupal::service('image.factory')->get(drupal_get_path('module', 'textimage') . '/misc/images/base.png'); // @todo no longer possible to get dummy images
    //$canvas_object = clone $image;
    $canvas_object->getToolkit()->setResource($newcanvas);
    image_overlay($image, $canvas_object, $targetsize['left'], $targetsize['top'], 100, TRUE);
  }
  else {
    $image->getToolkit()->setResource($newcanvas);
  }

  return TRUE;
}

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
