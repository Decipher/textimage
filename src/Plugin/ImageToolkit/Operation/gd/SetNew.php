<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\SetNew.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Color;

/**
 * Defines GD2 set new image operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_set_new",
 *   toolkit = "gd",
 *   operation = "set_new",
 *   label = @Translation("Set a new image"),
 *   description = @Translation("Creates a new transparent resource and sets it for the image.")
 * )
 */
class SetNew extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'width' => array(
        'description' => 'The width of the image, in pixels',
      ),
      'height' => array(
        'description' => 'The height of the image, in pixels',
      ),
      'mimetype' => array(
        'description' => 'The MIME type of the image',
        'required' => FALSE,
        'default' => 'image/png',
      ),
      'transparent_color' => array(
        'description' => 'The RGB hex color for GIF transparency',
        'required' => FALSE,
        'default' => '#FFFFFF',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    if (!$res = imagecreatetruecolor($arguments['width'], $arguments['height'])) {
      return FALSE;
    }
    switch ($arguments['mimetype']) {
      case 'image/png':
        imagealphablending($res, FALSE);
        $transparency = imagecolorallocatealpha($res, 0, 0, 0, 127);
        imagefill($res, 0, 0, $transparency);
        imagealphablending($res, TRUE);
        imagesavealpha($res, TRUE);
        $this->getToolkit()->setType(IMAGETYPE_PNG);
        break;

      case 'image/gif':
        if (empty($arguments['transparent_color'])) {
          // No transparency color specified, fill white.
          $fill_color = imagecolorallocate($res, 255, 255, 255);
        }
        else {
          $fill_rgb = Color::hexToRgb($arguments['transparent_color']);
          $fill_color = imagecolorallocate($res, $fill_rgb['red'], $fill_rgb['green'], $fill_rgb['blue']);
          imagecolortransparent($res, $fill_color);
        }
        imagefill($res, 0, 0, $fill_color);
        $this->getToolkit()->setType(IMAGETYPE_GIF);
        break;

      case 'image/jpeg':
        imagefill($res, 0, 0, imagecolorallocate($res, 255, 255, 255));
        $this->getToolkit()->setType(IMAGETYPE_JPEG);
        break;

      default:
        return FALSE;

    }

    $this->getToolkit()->setResource($res);
    //$this->getToolkit()->getImage()->setValid(TRUE); // @todo
    return TRUE;
  }

}
