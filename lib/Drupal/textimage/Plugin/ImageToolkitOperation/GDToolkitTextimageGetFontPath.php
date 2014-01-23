<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkitOperation\GDToolkitTextimageGetFontPath.
 */

namespace Drupal\textimage\Plugin\ImageToolkitOperation;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Image\ImageInterface;
use Drupal\system\Annotation\ImageToolkitOperation;
use Drupal\system\Plugin\ImageToolkitInterface;
use Drupal\system\Plugin\ImageToolkitOperationBase;

/**
 * Defines GD2 Crop operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_get_font_path",
 *   label = @Translation("Get font file path"),
 *   description = @Translation("GD2 toolkit get font file path"),
 *   toolkit = "gd",
 *   operation = "textimage_get_font_path",
 *   weight = 0
 * )
 */
class GDToolkitTextimageGetFontPath extends ImageToolkitOperationBase {

  /**
   * Return the path of the font file, in a format usable by GD.
   *
   * @param \Drupal\system\Plugin\ImageToolkitInterface $toolkit
   *   An image toolkit.
   * @param \Drupal\Core\Image\ImageInterface $image
   *   An image object.
   * @param array $data
   *   An array of data to be used by the toolkit operation.
   *   - font_uri: The URI of the font file.
   *
   * @return string
   *   The file path.
   */
  public function process(ImageToolkitInterface $toolkit, ImageInterface $image, array $data = array()) {
    $font_wrapper = file_stream_wrapper_get_instance_by_uri($data['font_uri']);
    if ($font_wrapper instanceof \Drupal\Core\StreamWrapper\LocalStream) {
      return $font_wrapper->realpath(); // @todo remove
    }
    else {
      return is_file($data['font_uri']) ? $data['font_uri'] : NULL;
    }
  }

}
