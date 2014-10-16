<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageTextToImage.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\BoundingBox;
use Drupal\textimage\Component\ColorUtility;

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
      'inner_width' => array(
        'description' => '@todo',
      ),
      'inner_height' => array(
        'description' => '@todo',
      ),
      'inner_basepoint' => array(
        'description' => '@todo',
      ),
      'topLeftCornerPosition' => array(
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
        'points' => $arguments['outer_box'],
        'fill_color' => $arguments['layout']['background_color'],
      );
      $this->getToolkit()->apply('textimage_draw_polygon', $data);
    }

    // In debug mode, visually display the text boxes.
    if ($arguments['debug_visuals']) {
      // Inner box.
      $data = array(
        'points' => $arguments['inner_box'],
        'border_color' => $arguments['layout']['background_color'],
        'border_color_luma' => TRUE,
      );
      $this->getToolkit()->apply('textimage_draw_polygon', $data);
      // Outer box.
      $data = array(
        'points' => $arguments['outer_box'],
        'border_color' => $arguments['layout']['background_color'],
        'border_color_luma' => TRUE,
      );
      $this->getToolkit()->apply('textimage_draw_polygon', $data);
      // Wrapper.
      $data = array(
        'points' => array(
          0, 0,
          $this->getToolkit()->getWidth() - 1, 0,
          $this->getToolkit()->getWidth() - 1, $this->getToolkit()->getHeight() - 1,
          0, $this->getToolkit()->getHeight() - 1,
        ),
        'border_color' => '#000000',
      );
      $this->getToolkit()->apply('textimage_draw_polygon', $data);
    }

    // Foreground text color.
    $foreground_color = $this->getImageColor($arguments['font']['color']);

    // Determine if outline/shadow is required.
    $outline = $shadow = FALSE;
    if ($arguments['font']['stroke_mode'] == 'outline' && ($arguments['font']['outline_top'] || $arguments['font']['outline_right'] || $arguments['font']['outline_bottom'] || $arguments['font']['outline_left']) && $arguments['font']['stroke_color']) {
      $outline = TRUE;
    }
    elseif ($arguments['font']['stroke_mode'] == 'shadow' && ($arguments['font']['shadow_x_offset'] || $arguments['font']['shadow_y_offset'] || $arguments['font']['shadow_width'] || $arguments['font']['shadow_height']) && $arguments['font']['stroke_color']) {
      $shadow = TRUE;
    }

    // Process each of the text lines.
    $current_y = 0;
    foreach ($arguments['text_lines'] as $text_line) {

      // This text line's box size.
      $text_line_box = $this->getTextBoundingBox($text_line, 1, $arguments['font']['size'], $arguments['font']['uri']);
      $text_line_box->set('height', $arguments['line_height']);

      // Manage text alignment within the line.
      $x_delta = $arguments['inner_width'] - $text_line_box->get('width');
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
      $text_line_box_t = $text_line_box->getTranslatedBox(
        $arguments['font']['angle'],
        array(
          $arguments['layout']['padding_left'] + $x_offset,
          $arguments['layout']['padding_top'] + $current_y - $arguments['line_height'],
        ),
        $arguments['topLeftCornerPosition']
      );
      list($x_pos, $y_pos) = $text_line_box_t->get('basepoint');

      // Overlays the text outline/shadow, if required.
      if ($outline || $shadow) {
        $stroke_color = $this->getImageColor($arguments['font']['stroke_color']);
        if ($outline) {
          $stroke_x_pos = $x_pos;
          $stroke_y_pos = $y_pos;
          $stroke_top = $arguments['font']['outline_top'];
          $stroke_right = $arguments['font']['outline_right'];
          $stroke_bottom = $arguments['font']['outline_bottom'];
          $stroke_left = $arguments['font']['outline_left'];
        }
        elseif ($shadow) {
          $stroke_x_pos = $x_pos + $arguments['font']['shadow_x_offset'];
          $stroke_y_pos = $y_pos + $arguments['font']['shadow_y_offset'];
          $stroke_top = 0;
          $stroke_right = $arguments['font']['shadow_width'];
          $stroke_bottom = $arguments['font']['shadow_height'];
          $stroke_left = 0;
        }
        $data_stroke = array(
          'size'        => $arguments['font']['size'],
          'angle'       => -$arguments['font']['angle'],
          'fontfile'    => $arguments['font']['uri'],
          'text'        => $text_line,
          'x'           => $stroke_x_pos,
          'y'           => $stroke_y_pos,
          'textcolor'   => $foreground_color,
          'strokecolor' => $stroke_color,
          'top'         => $stroke_top,
          'right'       => $stroke_right,
          'bottom'      => $stroke_bottom,
          'left'        => $stroke_left,
        );
        $this->getToolkit()->apply('textimage_text_stroke', $data_stroke);
      }

      // Overlays the text.
      imagettftext(
        $this->getToolkit()->getResource(),
        $arguments['font']['size'],
        -$arguments['font']['angle'],
        $x_pos,
        $y_pos,
        $foreground_color,
        $this->getFontPath($arguments['font']['uri']),
        $text_line
      );

      // In debug mode, display a polygon enclosing the text line.
      if ($arguments['debug_visuals']) {
        $this->drawDebugBox($text_line_box_t, $arguments['layout']['background_color'], TRUE);
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
  protected function drawDebugBox(BoundingBox $box, $rgba, $luma = FALSE) {

    // Check color.
    if (!$rgba) {
      $rgba = '#00000000';
    }
    elseif ($luma) {
      $rgba = ColorUtility::matchLuma($rgba);
    }

    // Retrieve points.
    $points = $box->get('points');

    // Draw box.
    $data = array(
      'points' => $points,
      'border_color' => $rgba,
    );
    $this->getToolkit()->apply('textimage_draw_polygon', $data);

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
    $orange = '#FF640000';
    $yellow = '#FFFF0000';
    $green  = '#00FF0000';
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
    $basepoint = $box->get('basepoint');
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
