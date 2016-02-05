<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageTextToWrapper.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Image\ImageInterface;
use Drupal\system\Plugin\ImageToolkit\Operation\gd\GDImageToolkitOperationBase;
use Drupal\image_effects\Component\ColorUtility;
use Drupal\image_effects\Component\PositionedRectangle;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\gd\GDOperationTrait;
use Drupal\textimage\Component\TextUtility;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageTextToWrapperTrait;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;

/**
 * Defines Textimage GD2 text-to-wrapper operation.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_text_to_wrapper",
 *   toolkit = "gd",
 *   operation = "textimage_text_to_wrapper",
 *   label = @Translation("Overlays text over an image"),
 *   description = @Translation("Overlays text over a GD resource.")
 * )
 */
class TextimageTextToWrapper extends GDImageToolkitOperationBase {

  use TextimageOperationTrait;
  use GDOperationTrait;
  use TextimageTextToWrapperTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Determine if outline/shadow is required.
    $outline = $shadow = FALSE;
    if ($arguments['font']['stroke_mode'] == 'outline' && ($arguments['font']['outline_top'] || $arguments['font']['outline_right'] || $arguments['font']['outline_bottom'] || $arguments['font']['outline_left']) && $arguments['font']['stroke_color']) {
      $outline = TRUE;
    }
    elseif ($arguments['font']['stroke_mode'] == 'shadow' && ($arguments['font']['shadow_x_offset'] || $arguments['font']['shadow_y_offset'] || $arguments['font']['shadow_width'] || $arguments['font']['shadow_height']) && $arguments['font']['stroke_color']) {
      $shadow = TRUE;
    }

    // Add stroke to padding to ensure inner box includes entire font space.
    if ($outline) {
      $arguments['layout']['padding_top'] += $arguments['font']['outline_top'];
      $arguments['layout']['padding_right'] += $arguments['font']['outline_right'];
      $arguments['layout']['padding_bottom'] += $arguments['font']['outline_bottom'];
      $arguments['layout']['padding_left'] += $arguments['font']['outline_left'];
    }
    elseif ($shadow) {
      $arguments['layout']['padding_top'] += ($arguments['font']['shadow_y_offset'] < 0 ? -$arguments['font']['shadow_y_offset'] : 0);
      $arguments['layout']['padding_right'] += ($arguments['font']['shadow_x_offset'] > 0 ? $arguments['font']['shadow_x_offset'] : 0);
      $arguments['layout']['padding_bottom'] += ($arguments['font']['shadow_y_offset'] > 0 ? $arguments['font']['shadow_y_offset'] : 0);
      $arguments['layout']['padding_left'] += ($arguments['font']['shadow_x_offset'] < 0 ? -$arguments['font']['shadow_x_offset'] : 0);
      $shadow_width = ($arguments['font']['shadow_x_offset'] != 0) ? $arguments['font']['shadow_width'] + 1 : $arguments['font']['shadow_width'];
      $shadow_height = ($arguments['font']['shadow_y_offset'] != 0) ? $arguments['font']['shadow_height'] + 1 : $arguments['font']['shadow_height'];
      $net_right = $shadow_width + ($arguments['font']['shadow_x_offset'] >= 0 ? 0 : $arguments['font']['shadow_x_offset']);
      $arguments['layout']['padding_right'] += ($net_right > 0 ? $net_right : 0);
      $net_bottom = $shadow_height + ($arguments['font']['shadow_y_offset'] >= 0 ? 0 : $arguments['font']['shadow_y_offset']);
      $arguments['layout']['padding_bottom'] += ($net_bottom > 0 ? $net_bottom : 0);
    }

    // Perform text wrapping, if necessary.
    if ($arguments['text']['maximum_width'] > 0) {
      $arguments['text_string'] = $this->wrapText(
        $arguments['text_string'],
        $arguments['font']['size'],
        $arguments['font']['uri'],
        $arguments['text']['maximum_width'] - $arguments['layout']['padding_left'] - $arguments['layout']['padding_right'] - 1,
        $arguments['text']['align']
      );
    }

    // Load text lines to array elements.
    $text_lines = explode("\n", $arguments['text_string']);
    $num_lines = count($text_lines);

    // Calculate bounding boxes.
    // ---------------------------------------
    // Inner box   - the exact bounding box of the text.
    // Outer box   - the box where the inner box is - can be different because
    //               of padding.
    // Wrapper     - the canvas where the outer box is laid.
    // ---------------------------------------

    // Get inner box details, for horizontal text, unpadded.
    // If fixed width, set to configuration, otherwise get width from the font
    // bounding box.
    if ($arguments['text']['fixed_width'] && !empty($arguments['text']['maximum_width'])) {
      $inner_box_width = $arguments['text']['maximum_width'] - $arguments['layout']['padding_left'] - $arguments['layout']['padding_right'];
    }
    else {
      $inner_box_width = $this->getTextWidth($arguments['text_string'], $arguments['font']['size'], $arguments['font']['uri']);
    }

    // Determine line height.
    $height_info = $this->getTextHeightInfo($arguments['font']['size'], $arguments['font']['uri']);
    $line_height = $height_info['height'];

    // Manage leading (line spacing), adding total line spacing to height.
    $inner_box_height = ($height_info['height'] * $num_lines) + ($arguments['text']['line_spacing'] * ($num_lines - 1));

    // Get outer box.
    $outer_rect = new PositionedRectangle($inner_box_width + $arguments['layout']['padding_right'] + $arguments['layout']['padding_left'], $inner_box_height + $arguments['layout']['padding_top'] + $arguments['layout']['padding_bottom']);
    $outer_rect->rotate($arguments['font']['angle']);
    $outer_rect->translate($outer_rect->getRotationOffset());

    // Get inner box.
    $inner_rect = new PositionedRectangle($inner_box_width, $inner_box_height);
    $inner_rect->translate([$arguments['layout']['padding_left'], $arguments['layout']['padding_top']]);
    $inner_rect->rotate($arguments['font']['angle']);
    $inner_rect->translate($outer_rect->getRotationOffset());

    // Set image dimensions to allow fitting the text. Explicitly setting
    // extension to 'png' to ensure wrapper is full transparent alpha channel
    // enabled.
    $data = [
      'width' => $outer_rect->getBoundingWidth(),
      'height' => $outer_rect->getBoundingHeight(),
      'extension' => 'png',
      'is_temp' => FALSE,
    ];
    if (!$this->getToolkit()->apply('create_new', $data)) {
      return FALSE;
    }

    // Draw and fill the outer text box, if required.
    if ($arguments['layout']['background_color']) {
      $data_rectangle = array(
        'rectangle' => $outer_rect,
        'fill_color' => $arguments['layout']['background_color'],
      );
      $this->getToolkit()->apply('draw_rectangle', $data_rectangle);
    }

    // In debug mode, visually display the text boxes.
    if ($arguments['debug_visuals']) {
      // Inner box.
      $data = array(
        'rectangle' => $inner_rect,
        'border_color' => $arguments['layout']['background_color'] ?: '#FFFFFF',
        'border_color_luma' => TRUE,
      );
      $this->getToolkit()->apply('draw_rectangle', $data);
      // Outer box.
      $data = array(
        'rectangle' => $outer_rect,
        'border_color' => $arguments['layout']['background_color'] ?: '#FFFFFF',
        'border_color_luma' => TRUE,
      );
      $this->getToolkit()->apply('draw_rectangle', $data);
      // Wrapper.
      $data = array(
        'rectangle' => new PositionedRectangle($this->getToolkit()->getWidth(), $this->getToolkit()->getHeight()),
        'border_color' => '#000000',
      );
      $this->getToolkit()->apply('draw_rectangle', $data);
    }

    // Process each of the text lines.
    $current_y = 0;
    foreach ($text_lines as $text_line) {
      // This text line's width.
      $text_line_width = $this->getTextWidth($text_line, $arguments['font']['size'], $arguments['font']['uri']);
      $text_line_rect = new PositionedRectangle($text_line_width, $line_height);
      $text_line_rect->setPoint('basepoint', $height_info['basepoint']);

      // Manage text alignment within the line.
      $x_delta = $inner_rect->getWidth() - $text_line_rect->getWidth();
      $current_y += $line_height;
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
      $text_line_rect->translate([$arguments['layout']['padding_left'] + $x_offset, $arguments['layout']['padding_top'] + $current_y - $line_height]);
      $text_line_rect->rotate($arguments['font']['angle']);
      $text_line_rect->translate($outer_rect->getRotationOffset());

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

    // Resize the wrapper if needed.
    if ($arguments['layout']['overflow_action'] == 'scaletext') {
      $this->resizeWrapper($arguments);
    }

    return TRUE;
  }

  /**
   * Resizes the text wrapping image.
   *
   * @param array $arguments
   *   An associative array of arguments.
   */
  protected function resizeWrapper(array $arguments) {
    // Wrapper image dimensions.
    $original_wrapper_width = $this->getToolkit()->getWidth();
    $original_wrapper_height = $this->getToolkit()->getHeight();

    // Determine wrapper offset, based on placement option and direct
    // offset indicated in settings.
    $wrapper_xpos = ceil(image_filter_keyword($arguments['layout']['x_pos'], $arguments['canvas_width'], $original_wrapper_width)) + $arguments['layout']['x_offset'];
    $wrapper_ypos = ceil(image_filter_keyword($arguments['layout']['y_pos'], $arguments['canvas_height'], $original_wrapper_height)) + $arguments['layout']['y_offset'];

    // Position of wrapper's bottom right point.
    $xc_pos = $wrapper_xpos + $original_wrapper_width;
    $yc_pos = $wrapper_ypos + $original_wrapper_height;

    // Redetermine offset wrapper position and size based on
    // background image size.
    $wrapper_xpos = max(0, $wrapper_xpos);
    $wrapper_ypos = max(0, $wrapper_ypos);
    $xc_pos = min($arguments['canvas_width'], $xc_pos);
    $yc_pos = min($arguments['canvas_height'], $yc_pos);
    $wrapper_width = $xc_pos - $wrapper_xpos;
    $wrapper_height = $yc_pos - $wrapper_ypos;

    // If negative width/height, then the wrapper is totally
    // overflowing the background, and we cannot resize it.
    if ($wrapper_width < 0 || $wrapper_height < 0) {
      return;
    }

    // Determine if scaling needed. Take the side that is shrinking
    // most.
    $width_resize_index = $wrapper_width / $original_wrapper_width;
    $height_resize_index = $wrapper_height / $original_wrapper_height;
    if ($width_resize_index < 1 || $height_resize_index < 1) {
      if ($width_resize_index < $height_resize_index) {
        $wrapper_height = NULL;
      }
      else {
        $wrapper_width = NULL;
      }
      $this->getToolkit()->apply('scale', [
        'width' => $wrapper_width,
        'height' => $wrapper_height,
      ]);
    }
  }

  /**
   * Display a polygon enclosing the text line, and conspicuous points.
   *
   * Credit to Ruquay K Calloway
   *
   * @param \Drupal\image_effects\Component\PositionedRectangle $rect
   *   A PositionedRectangle object, including basepoint.
   * @param string $rgba
   *   RGBA color of the rectangle.
   * @param bool $luma
   *   if TRUE, convert RGBA to best match using luma.
   *
   * @see http://ruquay.com/sandbox/imagettf
   */
  protected function drawDebugBox(PositionedRectangle $rect, $rgba, $luma = FALSE) {

    // Check color.
    if (!$rgba) {
      $rgba = '#000000FF';
    }
    elseif ($luma) {
      $rgba = ColorUtility::matchLuma($rgba);
    }

    // Retrieve points.
    $points = $this->getRectangleCorners($rect);

    // Draw box.
    $data = array(
      'rectangle' => $rect,
      'border_color' => $rgba,
    );
    $this->getToolkit()->apply('draw_rectangle', $data);

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
    $basepoint = $rect->getPoint('basepoint');
    $data = array(
      'cx' => $basepoint[0],
      'cy' => $basepoint[1],
      'width' => $dotsize,
      'height' => $dotsize,
      'color' => $green,
    );
    $this->getToolkit()->apply('textimage_draw_ellipse', $data);
  }

  /**
   * Wrap text for rendering at a given width.
   *
   * @param string $text
   *   Text string in UTF-8 encoding.
   * @param int $font_size
   *   Font size.
   * @param string $font_uri
   *   URI of the TrueType font to use.
   * @param int $maximum_width
   *   Maximum width allowed for each line.
   *
   * @return string
   *   Text string, with newline characters to separate each line.
   */
  protected function wrapText($text, $font_size, $font_uri, $maximum_width) {
    // State variables for the search interval.
    $end = 0;
    $begin = 0;
    $fit = $begin;

    // Note: we count in bytes for speed reasons, but maintain character
    // boundaries.
    while (TRUE) {
      // Find the next wrap point (always after trailing whitespace).
      if (TextUtility::drupalPregMatch('/[' . TextUtility::PREG_CLASS_PUNCTUATION . '][' . TextUtility::PREG_CLASS_SEPARATOR . ']*|[' . TextUtility::PREG_CLASS_SEPARATOR . ']+/u', $text, $match, PREG_OFFSET_CAPTURE, $end)) {
        $end = $match[0][1] + Unicode::strlen($match[0][0]);
      }
      else {
        $end = Unicode::strlen($text);
      }

      // Fetch text, removing trailing white-space, and measure it.
      $line  = preg_replace('/[' . TextUtility::PREG_CLASS_SEPARATOR . ']+$/u', '', Unicode::substr($text, $begin, $end - $begin));
      $width = $this->getTextWidth($line, $font_size, $font_uri);

      // See if line extends past the available space.
      if ($width > $maximum_width) {
        // If this is the first word, we need to truncate it.
        if ($fit == $begin) {
          // Cut off letters until it fits.
          while (Unicode::strlen($line) > 0 && $width > $maximum_width) {
            $line  = Unicode::substr($line, 0, -1);
            $width = $this->getTextWidth($line, $font_size, $font_uri);
          }
          // If no fit was found, the image is too narrow.
          $fit = Unicode::strlen($line) ? $begin + Unicode::strlen($line) : $end;
        }
        // We have a valid fit for the next line. Insert a line-break and reset
        // the search interval.
        if (Unicode::substr($text, $fit - 1, 1) == ' ') {
          $first_part = Unicode::substr($text, 0, $fit - 1);
        }
        else {
          $first_part = Unicode::substr($text, 0, $fit);
        }
        $last_part  = Unicode::substr($text, $fit);
        $text  = $first_part . "\n" . $last_part;
        $begin = ++$fit;
        $end   = $begin;
      }
      else {
        // We can fit this text. Wait for now.
        $fit = $end;
      }

      if ($end == Unicode::strlen($text)) {
        // All text fits. No more changes are needed.
        break;
      }
    }
    return $text;
  }

  /**
   * Return the width of a text using TrueType fonts.
   *
   * @param string $text
   *   A text string.
   * @param string $font_size
   *   The font size.
   * @param string $font_uri
   *   The font URI.
   *
   * @return int
   *   The width of the text in pixels.
   */
  protected function getTextWidth($text, $font_size, $font_uri) {
    // Get fully qualified font file information.
    if (!$font_file = $this->getFontPath($font_uri)) {
      return NULL;
    }
    // Get the bounding box for $text to get width.
    $points = imagettfbbox($font_size, 0, $font_file, $text);
    // Return bounding box width.
    return (abs($points[4] - $points[6]) + 1);
  }

  /**
   * Return the height and basepoint of a text using TrueType fonts.
   *
   * Need to calculate the height independently from primitive as
   * lack of descending/ascending characters will limit the height.
   * So to have uniformity we take a dummy string with ascending and
   * descending characters to set to max height possible.
   *
   * @param string $font_size
   *   The font size.
   * @param string $font_uri
   *   The font URI.
   *
   * @return array
   *   An associative array with the following keys:
   *   - 'height' the text height in pixels.
   *   - 'basepoint' an array of x, y coordinates of the font's basepoint.
   */
  protected function getTextHeightInfo($font_size, $font_uri) {
    // Get fully qualified font file information.
    if (!$font_file = $this->getFontPath($font_uri)) {
      return NULL;
    }
    // Get the bounding box for $text to get height.
    $points = imagettfbbox($font_size, 0, $font_file, 'bdfhkltgjpqyBDFHKLTGJPQY§@çÅÀÈÉÌÒÇ');
    $height = (abs($points[5] - $points[1]) + 1);
    return [
      'height' => $height,
      'basepoint' => [$points[6], -$points[7]],
    ];
  }

}
