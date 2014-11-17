<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageTextToImage.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;
use Drupal\textimage\Component\Rectangle;

/**
 * Defines Textimage GD2 text-to-image operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_text_to_image",
 *   toolkit = "gd",
 *   operation = "textimage_text_to_image",
 *   label = @Translation("Overlays text over the image"),
 *   description = @Translation("Creates a new image resource and overlays the text over it.")
 * )
 */
class TextimageTextToImage extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'font' => array(
        'description' => '@todo',
      ),
      'layout' => array(
        'description' => '@todo',
      ),
      'text' => array(
        'description' => '@todo',
      ),
      'text_lines' => array(
        'description' => '@todo',
      ),
      'inner_basepoint' => array(
        'description' => '@todo',
      ),
      'inner_box' => array(
        'description' => '@todo',
      ),
      'outer_box' => array(
        'description' => '@todo',
      ),
      'line_height' => array(
        'description' => '@todo',
      ),
      'debug_visuals' => array(
        'description' => '@todo',
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
// $arguments['debug_visuals'] = TRUE; // @todo
    // Create the image resource, fill transparent.
    $ret = $this->getToolkit()->apply('create_new', array(
      'width' => $this->getToolkit()->getWidth(),
      'height' => $this->getToolkit()->getHeight(),
    ));
    if (!$ret) {
      return FALSE;
    }

    // Draw and fill the outer text box, if required.
    if ($arguments['layout']['background_color']) {
      $data = array(
        'rectangle' => $arguments['outer_box'],
        'fill_color' => $arguments['layout']['background_color'],
      );
      $this->getToolkit()->apply('textimage_draw_rectangle', $data);
    }

    // In debug mode, visually display the text boxes.
    if ($arguments['debug_visuals']) {
      // Inner box.
      $data = array(
        'rectangle' => $arguments['inner_box'],
        'border_color' => $arguments['layout']['background_color'],
        'border_color_luma' => TRUE,
      );
      $this->getToolkit()->apply('textimage_draw_rectangle', $data);
      // Outer box.
      $data = array(
        'rectangle' => $arguments['outer_box'],
        'border_color' => $arguments['layout']['background_color'],
        'border_color_luma' => TRUE,
      );
      $this->getToolkit()->apply('textimage_draw_rectangle', $data);
      // Wrapper.
      $data = array(
        'rectangle' => new Rectangle($this->getToolkit()->getWidth(), $this->getToolkit()->getHeight()),
        'border_color' => '#000000',
      );
      $this->getToolkit()->apply('textimage_draw_rectangle', $data);
    }

    // Process each of the text lines.
    $current_y = 0;
    foreach ($arguments['text_lines'] as $text_line) {

      // This text line's width.
      $text_line_width = $this->getTextWidth($text_line, $arguments['font']['size'], $arguments['font']['uri']);
      $text_line_rect = new Rectangle($text_line_width, $arguments['line_height']);
      $text_line_rect->setPoint('basepoint', $arguments['inner_basepoint']);

      // Manage text alignment within the line.
      $x_delta = $arguments['inner_box']->getWidth() - $text_line_rect->getWidth();
      $current_y += $arguments['line_height'];
      switch ($arguments['text']['align']) {
        case 'center':
          $x_offset = round($x_delta / 2);
          break;

        case 'right':
          $x_offset = $x_delta;
          break;

        case 'left':
        default:
          $x_offset = 0;
          break;

      }

      // Get details for the rotated/translated text line box.
      $text_line_rect->translate([$arguments['layout']['padding_left'] + $x_offset, $arguments['layout']['padding_top'] + $current_y - $arguments['line_height']]);
      $text_line_rect->rotate($arguments['font']['angle']);
      $text_line_rect->translate($arguments['outer_box']->getRotationOffset());

      // Overlay the text onto the image.
      $data = array(
        'font'        => $arguments['font'],
        'text'        => $text_line,
        'basepoint'   => $text_line_rect->getPoint('basepoint'),
      );
      $this->getToolkit()->apply('textimage_text_overlay', $data);

      // In debug mode, display a polygon enclosing the text line.
      if ($arguments['debug_visuals']) {
        $this->drawDebugBox($text_line_rect, $arguments['layout']['background_color'], TRUE);
      }

      // Add interline spacing (leading) before next iteration.
      $current_y += $arguments['text']['line_spacing'];
    }

    // Finalise image.
    imagealphablending($this->getToolkit()->getResource(), TRUE);
    imagesavealpha($this->getToolkit()->getResource(), TRUE);

    return TRUE;
  }

  /**
   * Display a polygon enclosing the text line, and conspicuous points.
   *
   * Credit to Ruquay K Calloway
   *
   * @param TextimageTextbox $box
   *   Textbox object to draw (inclusing basepoint).
   * @param string $rgba
   *   RGBA color of the rectangle.
   * @param bool $luma
   *   if TRUE, convert RGBA to best match using luma.
   *
   * @see http://ruquay.com/sandbox/imagettf
   */
  protected function drawDebugBox(Rectangle $box, $rgba, $luma = FALSE) {

    // Check color.
    if (!$rgba) {
      $rgba = '#000000FF';
    }
    elseif ($luma) {
      $rgba = ColorUtility::matchLuma($rgba);
    }

    // Retrieve points.
    $points = $this->getRectangleCorners($box);

    // Draw box.
    $data = array(
      'rectangle' => $box,
      'border_color' => $rgba,
    );
    $this->getToolkit()->apply('textimage_draw_rectangle', $data);

    // Draw diagonal.
    $data = array(
      'x1' => $points[0],
      'y1' => $points[1],
      'x2' => $points[4],
      'y2' => $points[5],
      'color' => $rgba,
    );
    $this->getToolkit()->apply('textimage_draw_line', $data);

    // Conspicuous points.
    $orange = '#FF6400FF';
    $yellow = '#FFFF00FF';
    $green  = '#00FF00FF';
    $dotsize = 6;

    // Box corners.
    for ($i = 0; $i < 8; $i += 2) {
      $col = $i < 4 ? $orange : $yellow;
      $data = array(
        'cx' => $points[$i],
        'cy' => $points[$i + 1],
        'width' => $dotsize,
        'height' => $dotsize,
        'color' => $col,
      );
      $this->getToolkit()->apply('textimage_draw_ellipse', $data);
    }

    // Font baseline.
    $basepoint = $box->getPoint('basepoint');
    $data = array(
      'cx' => $basepoint[0],
      'cy' => $basepoint[1],
      'width' => $dotsize,
      'height' => $dotsize,
      'color' => $green,
    );
    $this->getToolkit()->apply('textimage_draw_ellipse', $data);
  }

}
