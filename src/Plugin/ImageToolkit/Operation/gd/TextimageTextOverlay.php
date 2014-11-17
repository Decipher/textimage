<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageTextOverlay.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

/**
 * Defines Textimage GD2 text overlay operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_text_overlay",
 *   toolkit = "gd",
 *   operation = "textimage_text_overlay",
 *   label = @Translation("Textimage Text Stroke"),
 *   description = @Translation("Overlays a given text into the image.")
 * )
 */
class TextimageTextOverlay extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'font' => array(
        'description' => 'Font data',
      ),
      'text' => array(
        'description' => 'The text string in UTF-8 encoding',
      ),
      'basepoint' => array(
        'description' => 'The basepoint of the text to be overlaid',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $font_file = $this->getFontPath($arguments['font']['uri']);

    // Overlays the text outline/shadow, if required.
    // Credit to John Ciacia.
    // @see http://www.johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
    $outline = $shadow = FALSE;
    if ($arguments['font']['stroke_mode'] == 'outline' && ($arguments['font']['outline_top'] || $arguments['font']['outline_right'] || $arguments['font']['outline_bottom'] || $arguments['font']['outline_left']) && $arguments['font']['stroke_color']) {
      $outline = TRUE;
    }
    elseif ($arguments['font']['stroke_mode'] == 'shadow' && ($arguments['font']['shadow_x_offset'] || $arguments['font']['shadow_y_offset'] || $arguments['font']['shadow_width'] || $arguments['font']['shadow_height']) && $arguments['font']['stroke_color']) {
      $shadow = TRUE;
    }
    if ($outline || $shadow) {
      $stroke_color = $this->allocateColorFromRgba($arguments['font']['stroke_color']);
      if ($outline) {
        $stroke_x_pos = $arguments['basepoint'][0];
        $stroke_y_pos = $arguments['basepoint'][1];
        $stroke_top = $arguments['font']['outline_top'];
        $stroke_right = $arguments['font']['outline_right'];
        $stroke_bottom = $arguments['font']['outline_bottom'];
        $stroke_left = $arguments['font']['outline_left'];
      }
      elseif ($shadow) {
        $stroke_x_pos = $arguments['basepoint'][0] + $arguments['font']['shadow_x_offset'];
        $stroke_y_pos = $arguments['basepoint'][1] + $arguments['font']['shadow_y_offset'];
        $stroke_top = 0;
        $stroke_right = $arguments['font']['shadow_width'];
        $stroke_bottom = $arguments['font']['shadow_height'];
        $stroke_left = 0;
      }
      for ($c1 = ($stroke_x_pos - abs($stroke_left)); $c1 <= ($stroke_x_pos + abs($stroke_right)); $c1++) {
        for ($c2 = ($stroke_y_pos - abs($stroke_top)); $c2 <= ($stroke_y_pos + abs($stroke_bottom)); $c2++) {
          $bg = imagettftext(
            $this->getToolkit()->getResource(),
            $arguments['font']['size'],
            -$arguments['font']['angle'],
            $c1,
            $c2,
            $stroke_color,
            $font_file,
            $arguments['text']
          );
          if ($bg == FALSE) {
            return FALSE;
          }
        }
      }
    }

    // Overlays the text.
    imagettftext(
      $this->getToolkit()->getResource(),
      $arguments['font']['size'],
      -$arguments['font']['angle'],
      $arguments['basepoint'][0],
      $arguments['basepoint'][1],
      $this->allocateColorFromRgba($arguments['font']['color']),
      $font_file,
      $arguments['text']
    );

    return TRUE;
  }

}
