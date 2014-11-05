<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageTextStroke.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

/**
 * Defines Textimage GD2 text stroke operation.
 *
 * Credit to John Ciacia.
 *
 * @link http://www.johnciacia.com/2010/01/04/using-php-and-gd-to-add-border-to-text/
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_text_stroke",
 *   toolkit = "gd",
 *   operation = "textimage_text_stroke",
 *   label = @Translation("Textimage Text Stroke"),
 *   description = @Translation("Writes the outline/shadow of a given text into the image.")
 * )
 */
class TextimageTextStroke extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'size' => array(
        'description' => 'font size',
      ),
      'angle' => array(
        'description' => 'angle in degrees to rotate the text',
      ),
      'fontfile' => array(
        'description' => 'file path of TrueType font to use',
      ),
      'text' => array(
        'description' => 'The text string in UTF-8 encoding',
      ),
      'x' => array(
        'description' => 'Upper left corner of the text',
      ),
      'y' => array(
        'description' => 'Lower left corner of the text',
      ),
      'strokecolor' => array(
        'description' => 'the rgba color of the text border',
      ),
      'top' => array(
        'description' => 'number of pixels of the text border at top of text',
      ),
      'right' => array(
        'description' => 'number of pixels of the text border at right of text',
      ),
      'bottom' => array(
        'description' => 'number of pixels of the text border at bottom of text',
      ),
      'left' => array(
        'description' => 'number of pixels of the text border at left of text',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    $fontfile = $this->getFontPath($arguments['fontfile']);
    for ($c1 = ($arguments['x'] - abs($arguments['left'])); $c1 <= ($arguments['x'] + abs($arguments['right'])); $c1++) {
      for ($c2 = ($arguments['y'] - abs($arguments['top'])); $c2 <= ($arguments['y'] + abs($arguments['bottom'])); $c2++) {
        $bg = imagettftext(
          $this->getToolkit()->getResource(),
          $arguments['size'],
          $arguments['angle'],
          $c1,
          $c2,
          $arguments['strokecolor'],
          $fontfile,
          $arguments['text']
        );
        if ($bg == FALSE) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

}
