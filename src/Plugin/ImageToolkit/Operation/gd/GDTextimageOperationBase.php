<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\GDTextimageOperationBase.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\textimage\Component\ColorUtility;

/**
 * Base class for Textimage GD image toolkit operations.
 */
abstract class GDTextimageOperationBase extends GDImageToolkitOperationBase {

  /**
   * Gets a GD imagecolor.
   */
  protected function getImageColor($color) {
    if ($this->getToolkit()->getMimeType() == 'image/png') {
      list($r, $g, $b, $alpha) = array_values(ColorUtility::hexToRgba($color));
      return imagecolorallocatealpha($this->getToolkit()->getResource(), $r, $g, $b, $alpha);
    }
    else {
      list($r, $g, $b) = array_values(ColorUtility::hexToRgba($arguments['color']));
      return imagecolorallocate($image->getToolkit()->getResource(), $r, $g, $b);
    }
  }

}
