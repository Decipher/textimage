<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageEffect\TextimageBackground.
 */

namespace Drupal\textimage\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;

/**
 * Define the Textimage background canvas.
 *
 * @ImageEffect(
 *   id = "textimage_background",
 *   label = @Translation("Textimage background"),
 *   description = @Translation("Define size and background color of the Textimage, or a background image.")
 * )
 */
class TextimageBackground extends TextimageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_replace_recursive(
      array(
        'background_image' => array(
          'mode' => 'passthrough',
          'uri' => NULL,
          'fid' => 0,
        ),
        'background' => array(
          'color' => NULL,
          'repeat' => TRUE,
        ),
        'exact' => array(
          'width'    => '',
          'height'   => '',
          'xpos' => 'center',
          'ypos' => 'center',
          'dimensions' => 'scale',
          'crop' => 'center-center',
        ),
        'relative' => array(
          'leftdiff' => '',
          'rightdiff' => '',
          'topdiff' => '',
          'bottomdiff' => '',
        ),
      ),
      parent::defaultConfiguration()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, array &$form_state) {
    $form = array();

    // Background image mode.
    $form['background_image'] = array(
      '#name' => 'background_image',
      '#type' => 'fieldset',
      '#title' => $this->t('Background image'),
      '#description' => $this->t('Select a background image to be used.'),
    );
    // Background image mode - options.
    $image_options = array(
      '' => $this->t('No background image.'),
      'passthrough' => $this->t('Inherit image from previous effect.'),
      'select' => $this->t('Select image.'),
    );
    $form['background_image']['mode'] = array(
      '#type' => 'radios',
      '#options' => $image_options,
      '#default_value' => $this->configuration['background_image']['mode'],
    );
    // Background image selection.
    $form['background_image'] += $this->backgroundPlugin->selectionElement('uri', $this->configuration);

    // Background color.
    $form['background'] = array(
      '#type' => 'details',
      '#title' => 'Background color',
      '#description'  => $this->t('Select the color you wish to use for the background of the image. This color will be placed around the image or fill the background if no image is selected.'),
    );
    $form['background']['color'] = array(
      '#type' => 'textimage_color',
      '#title' => $this->t('Color'),
      '#allow_transparent' => TRUE,
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['background']['color'],
    );
    $form['background']['repeat'] = array(
      '#type'  => 'checkbox',
      '#title' => $this->t('Use color for subsequent Textimage effects'),
      '#description' => $this->t('If checked, this color will be used as a filler in case Textimage effects applied later need to extend the size of the image.'),
      '#default_value' => $this->configuration['background']['repeat'],
    );

    // Background image exact dimensions.
    $form['exact'] = array(
      '#type' => 'details',
      '#title' => 'Exact size',
      '#description'  => $this->t('Set the background image to an exact size, either width or heigth. If only one of width or heigth is set, the other dimension will be automatically calculated based on resize/scale/crop options.'),
    );
    if (!$this->configuration['exact']['width'] && !$this->configuration['exact']['height']) {
      $form['exact']['#collapsed'] = TRUE;
    }
    $form['exact']['width'] = array(
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Width'),
      '#default_value' => $this->configuration['exact']['width'],
      '#description' => $this->t('Enter a value in pixels.'),
      '#size' => 5,
      '#field_suffix' => 'px',
    );
    $form['exact']['height'] = array(
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Height'),
      '#default_value' => $this->configuration['exact']['height'],
      '#description' => $this->t('Enter a value in pixels.'),
      '#size' => 5,
      '#field_suffix' => 'px',
    );
    $form['exact']['position'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Position'),
      '#options' => array(
        'left-top'      => $this->t('Top') . ' ' . $this->t('Left'),
        'center-top'    => $this->t('Top') . ' ' . $this->t('Center'),
        'right-top'     => $this->t('Top') . ' ' . $this->t('Right'),
        'left-center'   => $this->t('Center') . ' ' . $this->t('Left'),
        'center-center' => $this->t('Center'),
        'right-center'  => $this->t('Center') . ' ' . $this->t('Right'),
        'left-bottom'   => $this->t('Bottom') . ' ' . $this->t('Left'),
        'center-bottom' => $this->t('Bottom') . ' ' . $this->t('Center'),
        'right-bottom'  => $this->t('Bottom') . ' ' . $this->t('Right'),
      ),
      '#theme' => 'image_anchor',
      '#default_value' => implode('-', array($this->configuration['exact']['xpos'], $this->configuration['exact']['ypos'])),
      '#description' => $this->t('Position of the image on the resulting canvas, if the background image selected is smaller than the canvas.'),
    );
    $form['exact']['dimensions'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Image overflow'),
      '#default_value' => $this->configuration['exact']['dimensions'],
      '#options' => array(
        'scale' => $this->t('Scale image to fit.'),
        'crop' => $this->t('Crop image.'),
        'resize' => $this->t('Resize image to fit - allowing possible distortion.'),
      ),
      '#description' => $this->t('Action to take when the background image selected is bigger size than the canvas.'),
    );
    $form['exact']['crop'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Crop anchor'),
      '#options' => array(
        'left-top'      => $this->t('Top') . ' ' . $this->t('Left'),
        'center-top'    => $this->t('Top') . ' ' . $this->t('Center'),
        'right-top'     => $this->t('Top') . ' ' . $this->t('Right'),
        'left-center'   => $this->t('Center') . ' ' . $this->t('Left'),
        'center-center' => $this->t('Center'),
        'right-center'  => $this->t('Center') . ' ' . $this->t('Right'),
        'left-bottom'   => $this->t('Bottom') . ' ' . $this->t('Left'),
        'center-bottom' => $this->t('Bottom') . ' ' . $this->t('Center'),
        'right-bottom'  => $this->t('Bottom') . ' ' . $this->t('Right'),
      ),
      '#theme' => 'image_anchor',
      '#default_value' => $this->configuration['exact']['crop'],
      '#description' => $this->t('Select which part of the selected background image should be cropped.'),
      '#states' => array(
        'visible' => array(
          ':input[name="data[exact][dimensions]"]' => array('value' => 'crop'),
        ),
      ),
    );

    // Background image relative dimensions.
    $form['relative'] = array(
      '#type' => 'details',
      '#title' => $this->t('Relative size'),
      '#description' => $this->t('Set the background image to a relative size, based on the original image dimensions. Use to add simple borders or expand by a fixed amount. Negative values will crop the image.'),
      'topdiff' => array(
        '#type' => 'number',
        '#title' => $this->t('Top'),
        '#default_value' => $this->configuration['relative']['topdiff'],
        '#size' => 6,
        '#description' => $this->t('Enter a value in pixels.'),
        '#field_suffix' => 'px',
      ),
      'rightdiff' => array(
        '#type' => 'number',
        '#title' => $this->t('Right'),
        '#default_value' => $this->configuration['relative']['rightdiff'],
        '#size' => 6,
        '#description' => $this->t('Enter a value in pixels.'),
        '#field_suffix' => 'px',
      ),
      'bottomdiff' => array(
        '#type' => 'number',
        '#title' => $this->t('Bottom'),
        '#default_value' => $this->configuration['relative']['bottomdiff'],
        '#size' => 6,
        '#description' => $this->t('Enter a value in pixels.'),
        '#field_suffix' => 'px',
      ),
      'leftdiff' => array(
        '#type' => 'number',
        '#title' => $this->t('Left'),
        '#default_value' => $this->configuration['relative']['leftdiff'],
        '#size' => 6,
        '#description' => $this->t('Enter a value in pixels.'),
        '#field_suffix' => 'px',
      ),
    );
    if (!$this->configuration['relative']['leftdiff'] && !$this->configuration['relative']['rightdiff'] && !$this->configuration['relative']['topdiff'] && !$this->configuration['relative']['bottomdiff']) {
      $form['relative']['#collapsed'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, array &$form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $v = &$form_state['values'];
    if ($v['background_image']['mode'] <> 'select') {
      unset(
        $v['background_image']['fid'],
        $v['background_image']['uri']
      );
    }
    list($v['exact']['xpos'], $v['exact']['ypos']) = explode('-', $v['exact']['position']);
    unset ($v['exact']['position']);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    if ($data['background']['color']) {
      $data['background_color_detail'] = array(
        '#theme' => 'textimage_color_detail',
        '#color' => $data['background']['color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      );
    }
    return array(
      '#theme' => 'textimage_background_effect_summary',
      '#data' => $data,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {

    // Handle background image.
    switch ($this->configuration['background_image']['mode']) {
      // If a background image is selected, it will override any
      // image built thus far.
      case 'select':
        $background_image = $this->imageFactory->get($this->configuration['background_image']['uri']);
        if (!$image->apply('textimage_replace_image', array('replacement_image' => $background_image))) {
          return FALSE;
        }
        break;

      // If passing through the image from the last effect, no special
      // treatment needed.
      case 'passthrough':
        break;

      // If explicitly requested not to have a background image, create
      // a transparent 1x1 image.
      case '':
      default:
        $image->apply('textimage_create_transparent', array(
          'width' => 1,
          'height' => 1,
          'transparent' => $this->textimageFactory->getState('gif_transparency_color'),
        ));
        break;

    }

    $success = TRUE;

    // Handle exact sizing impacts on original image.
    if ($this->configuration['exact']['width'] || $this->configuration['exact']['height']) {
      // If current image is larger than size requested, call out to
      // scale/resize/crop operations to reduce image size depending
      // on options selected.
      if ($this->configuration['exact']['width'] <= $image->getWidth() || $this->configuration['exact']['height'] <= $image->getHeight()) {

        switch ($this->configuration['exact']['dimensions']) {
          case 'scale':
            $success = $image->scale($this->configuration['exact']['width'], $this->configuration['exact']['height']);
            break;

          case 'resize':
            $width = $this->configuration['exact']['width'] ?: $image->getWidth();
            $height = $this->configuration['exact']['height'] ?: $image->getHeight();
            $success = $image->resize($width, $height);
            break;

          case 'crop':
            $width = min($this->configuration['exact']['width'], $image->getWidth());
            $height = min($this->configuration['exact']['height'], $image->getHeight());
            list($x, $y) = explode('-', $this->configuration['exact']['crop']);
            $x = image_filter_keyword($x, $image->getWidth(), $width);
            $y = image_filter_keyword($y, $image->getHeight(), $height);
            $success = $image->crop($x, $y, $width, $height);
            break;

        }

        if (!$success) {
          _textimage_diag($this->t('Textimage failed image processing.'), WATCHDOG_ERROR, __FUNCTION__);
          return FALSE;
        }
      }
    }

    // If resizing, apply textimage_define_canvas to finalise layout.
    if ($this->configuration['exact']['width'] || $this->configuration['exact']['height'] || $this->configuration['relative']['leftdiff'] || $this->configuration['relative']['rightdiff'] || $this->configuration['relative']['topdiff'] || $this->configuration['relative']['bottomdiff']) {
      $canvas_data = array(
        'RGB' => array(
          'HEX' => $this->configuration['background']['color'],
        ),
        'under' => TRUE,
        'exact' => array(
          'width' => $this->configuration['exact']['width'],
          'height' => $this->configuration['exact']['height'],
          'xpos' => $this->configuration['exact']['xpos'],
          'ypos' => $this->configuration['exact']['ypos'],
        ),
        'relative' => array(
          'leftdiff' => $this->configuration['relative']['leftdiff'],
          'rightdiff' => $this->configuration['relative']['rightdiff'],
          'topdiff' => $this->configuration['relative']['topdiff'],
          'bottomdiff' => $this->configuration['relative']['bottomdiff'],
        ),
      );

      if ($image->getMimeType() == 'image/gif') {
        // For .gif format, if transparent background set transparency color.
        if (!$canvas_data['RGB']['HEX']) {
          $canvas_data['RGB']['HEX'] = $this->textimageFactory->getState('gif_transparency_color');
        }
        $success = $image->apply('textimage_define_canvas', $canvas_data);
      }
      else {
        $success = $image->apply('textimage_define_canvas', $canvas_data);
      }
    }

    if (!$success) {
      _textimage_diag($this->t('Textimage failed image processing.'), WATCHDOG_ERROR, __FUNCTION__);
      return FALSE;
    }

    // Stores background color for later effects.
    if ($this->configuration['background']['repeat']) {
      $this->textimageFactory->setState('background_color', $this->configuration['background']['color']);
    }

    // Reset transparency color for .gif format.
    if ($image->getMimeType() == 'image/gif') {
      $image->apply('textimage_set_transparency', array('color' => $this->textimageFactory->getState('gif_transparency_color')));
    }

    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions) {

    // If exact size WxH, set and return.
    if ($this->configuration['exact']['width'] and $this->configuration['exact']['height']) {
      $dimensions['width'] = $this->configuration['exact']['width'];
      $dimensions['height'] = $this->configuration['exact']['height'];
      return;
    }

    // Fetches WxH of the background image.
    switch ($this->configuration['background_image']['mode']) {
      case 'select':
        $image = $this->imageFactory->get($this->configuration['background_image']['uri']);
        if (!$image->isValid()) {
          _textimage_diag($this->t('Textimage failed loading image %image', array('%image' => $this->configuration['background_image']['uri'])), WATCHDOG_ERROR, __FUNCTION__);
          return;
        }
        $width = $image->getWidth();
        $height = $image->getHeight();
        break;

      // If passing through the image from the last effect, retain
      // the size.
      case 'passthrough':
        $width = $dimensions['width'];
        $height = $dimensions['height'];
        break;

      // If explicitly requested not to have a background image, WxH is 1x1.
      case '':
      default:
        $width = $height = 1;
        break;

    }

    if ($this->configuration['exact']['width'] or $this->configuration['exact']['height']) {
      // Handle exact sizing.
      if ($this->configuration['exact']['width'] <= $width or $this->configuration['exact']['height'] <= $height) {
        // If current image is larger than size requested, call out to
        // scale/resize/crop effects to reduce image size depending
        // on options selected.
        switch ($this->configuration['exact']['dimensions']) {
          case 'scale':
            $scale_data = array(
              'width' => $this->configuration['exact']['width'],
              'height' => $this->configuration['exact']['height'],
              'upscale' => 0,
            );
            image_scale_dimensions($dimensions, $scale_data);
            return;

          case 'resize':
            $resize_data = array(
              'width' => $this->configuration['exact']['width'] ? $this->configuration['exact']['width'] : $width,
              'height' => $this->configuration['exact']['height'] ? $this->configuration['exact']['height'] : $height,
            );
            image_resize_dimensions($dimensions, $resize_data);
            return;

          case 'crop':
            $crop_data = array(
              'width' => min($this->configuration['exact']['width'], $width),
              'height' => min($this->configuration['exact']['height'], $height),
              'anchor' => $this->configuration['exact']['crop'],
            );
            image_resize_dimensions($dimensions, $crop_data);
            return;

        }
        _textimage_diag($this->t('Textimage image dimensions resizing failed.'), WATCHDOG_ERROR, __FUNCTION__);
        return FALSE;
      }
    }
    elseif ($this->configuration['relative']['leftdiff'] or $this->configuration['relative']['rightdiff'] or $this->configuration['relative']['topdiff'] or $this->configuration['relative']['bottomdiff']) {
      // Handle relative size.
      $dimensions['width'] = $width + $this->configuration['relative']['leftdiff'] + $this->configuration['relative']['rightdiff'];
      $dimensions['height'] = $height + $this->configuration['relative']['topdiff'] + $this->configuration['relative']['bottomdiff'];
      return;
    }
  }

}
