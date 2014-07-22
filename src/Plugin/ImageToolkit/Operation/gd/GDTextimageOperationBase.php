<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\GDTextimageOperationBase.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\textimage\Component\ColorUtility;

/**
 * Base class for Textimage GD image toolkit operations.
 */
abstract class GDTextimageOperationBase extends GDImageToolkitOperationBase {

  /**
   * Return the path of the font file, in a format usable by GD.
   */
  public static function getFontPath($font_uri) { // @todo change to protected non-static
    $font_wrapper = file_stream_wrapper_get_instance_by_uri($font_uri);
    if ($font_wrapper instanceof LocalStream) {
      $ret = $font_wrapper->realpath(); // @todo remove
    }
    else {
      $ret = is_file($font_uri) ? $font_uri : NULL;
    }
    if (!$ret) {
      _textimage_diag($this->t("Textimage could not find the font file @fontfile.", array('@fontfile' => $font_uri)), WATCHDOG_ERROR, __FUNCTION__);
    }
    return $ret;
  }

  /**
   * Gets a GD imagecolor.
   */
  protected function getImageColor($color) {
    if ($this->getToolkit()->getMimeType() == 'image/png') {
      list($r, $g, $b, $alpha) = array_values(ColorUtility::hexToRgba($color));
      return imagecolorallocatealpha($this->getToolkit()->getResource(), $r, $g, $b, $alpha);
    }
    else {
      list($r, $g, $b) = array_values(ColorUtility::hexToRgba($color));
      return imagecolorallocate($this->getToolkit()->getResource(), $r, $g, $b);
    }
  }

}
