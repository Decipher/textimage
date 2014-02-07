<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageEffect\TextimageText.
 */

namespace Drupal\textimage\Plugin\ImageEffect;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectInterface;
use Drupal\image\ImageEffectBase;
use Drupal\textimage\Component\BoundingBox;
use Drupal\textimage\Component\TextUtility;
use Drupal\textimage\Component\ColorUtility;
use Drupal\textimage\Entity\TextimageStyle;

/**
 * Define the Textimage text.
 *
 * @ImageEffect(
 *   id = "textimage_text",
 *   label = @Translation("Textimage text"),
 *   description = @Translation("Define text font, size and positioning.")
 * )
 */
class TextimageText extends ImageEffectBase implements ConfigurableImageEffectInterface {

  // @todo
  protected $effectsFactory;
  protected $textimageFactory;
  protected $fontPlugin;
  // @todo inject configuration

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->effectsFactory = \Drupal::service('plugin.manager.image.effect');
    $this->textimageFactory = \Drupal::service('textimage.factory');
    $this->fontPlugin = \Drupal::service('plugin.manager.textimage.font')->getPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_replace_recursive(
      array(
        'font'          => array(
          'name'                  => config('textimage.settings')->get('default_font.name'),
          'uri'                   => config('textimage.settings')->get('default_font.uri'),
          'size'                  => 16,
          'angle'                 => 0,
          'color'                 => '#00000000',
          'stroke_mode'           => 'outline',
          'stroke_color'          => '#00000000',
          'outline_top'           => 0,
          'outline_right'         => 0,
          'outline_bottom'        => 0,
          'outline_left'          => 0,
          'shadow_x_offset'       => 1,
          'shadow_y_offset'       => 1,
          'shadow_width'          => 0,
          'shadow_height'         => 0,
        ),
        'layout'       => array(
          'padding_top'           => 0,
          'padding_right'         => 0,
          'padding_bottom'        => 0,
          'padding_left'          => 0,
          'x_pos'                 => 'center',
          'y_pos'                 => 'center',
          'x_offset'              => 0,
          'y_offset'              => 0,
          'background_color'      => NULL,
          'overflow_action'       => 'extend',
        ),
        'text' => array(
          'maximum_width'         => 0,
          'fixed_width'           => FALSE,
          'align'                 => 'left',
          'line_spacing'          => 0,
          'case_format'           => '',
        ),
        'text_string'             => $this->t('Preview'),
      ),
      parent::defaultConfiguration()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getForm() {

    // --- Preview effect.
    $this->configuration['preview_bar']['debug_visuals'] = empty($this->configuration['preview_bar']['debug_visuals']) ? FALSE : TRUE;
    $form['preview'] = array(
      '#type'   => 'item',
      '#markup' => '<strong>' . $this->t('Preview') . ":</strong>\n" . '<div id="textimage-preview">' . $this->previewImage($this->configuration) . '</div>',
    );

    // --- Preview bar.
    $form['preview_bar'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('container-inline'),
      ),
    );
    // Refresh button.
    $form['preview_bar']['preview'] = array(
      '#type'  => 'button',
      '#value' => $this->t('Refresh preview'),
      '#ajax'  => array(
        'callback' => array($this, 'processAjaxPreview'),
      ),
    );
    // Visual aids.
    $form['preview_bar']['debug_visuals'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Visual aids in preview'),
      '#default_value' => FALSE,
    );

    // --- Settings.
    $form['settings'] = array(
      '#type' => 'vertical_tabs',
      '#tree' => FALSE,
    );

    // --- Text default.
    $form['text_default'] = array(
      '#type'  => 'details',
      '#title' => $this->t('Text default'),
      '#collapsed'   => FALSE,
      '#group'   => 'settings',
    );
    $form['text_default']['text_string'] = array(
      '#type'  => 'textarea',
      '#title' => $this->t('Default text'),
      '#default_value' => $this->configuration['text_string'],
      '#description' => $this->t('Enter the default text string for this effect. You can also enter tokens, that Textimage will resolve when applying the effect.'),
      '#rows' => 3,
      '#required' => TRUE,
    );
    /* @todo when module is available if (_textimage_module_exists('token', TEXTIMAGE_TOKEN_MIN_VERSION)) {
      $form['text_default']['tokens'] = array(
        '#theme' => 'token_tree',
        '#token_types' => array('node', 'user', 'file', 'textimage'),
        '#global_types' => TRUE,
        '#click_insert' => TRUE,
      );
    }*/

    // ---- Font settings.
    $form['font'] = array(
      '#type'  => 'details',
      '#title' => $this->t('Font settings'),
      '#collapsed'   => FALSE,
      '#group'   => 'settings',
    );
    $form['font'] += $this->fontPlugin->selectionElement('name', array(
      '#title' => $this->t('Font'),
      '#description' => $this->t('Select the font to be used in this image.'),
    ));
    $form['font']['size'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Size'),
      '#description'   => $this->t('Enter the size of the text to be generated.'),
      '#default_value' => $this->configuration['font']['size'],
      '#maxlength' => 5,
      '#size' => 3,
      '#required' => TRUE,
      '#min' => 1,
    );
    $form['font']['angle'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Rotation'),
      '#maxlength' => 4,
      '#size' => 4,
      '#field_suffix' => $this->t('&deg;'),
      '#description' => $this->t('Enter the angle in degrees at which the text will be displayed. Positive numbers rotate the text clockwise, negative numbers counter-clockwise.'),
      '#default_value' => $this->configuration['font']['angle'],
      '#min' => -360,
      '#max' => 360,
    );
    $form['font']['color'] = array(
      '#type' => 'textimage_color',
      '#title' => $this->t('Font color'),
      '#description'  => $this->t('Set the font color.'),
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['font']['color'],
    );
    // Outline.
    $form['font']['stroke'] = array(
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#title' => $this->t('Outline / Shadow'),
      '#description'   => $this->t('Optionally add an outline or shadow around the font. Enter the information in pixels.'),
    );
    $stroke_options = array(
      'outline' => $this->t('Outline'),
      'shadow' => $this->t('Shadow'),
    );
    $form['font']['stroke']['mode'] = array(
      '#type'    => 'radios',
      '#title'   => $this->t('Mode'),
      '#options' => $stroke_options,
      '#default_value' => $this->configuration['font']['stroke_mode'],
    );
    $form['font']['stroke']['top'] = array(
      '#type' => 'number',
      '#title' => $this->t('Top'),
      '#default_value' => $this->configuration['font']['outline_top'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'outline'),
        ),
      ),
    );
    $form['font']['stroke']['right'] = array(
      '#type' => 'number',
      '#title' => $this->t('Right'),
      '#default_value' => $this->configuration['font']['outline_right'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'outline'),
        ),
      ),
    );
    $form['font']['stroke']['bottom'] = array(
      '#type' => 'number',
      '#title' => $this->t('Bottom'),
      '#default_value' => $this->configuration['font']['outline_bottom'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'outline'),
        ),
      ),
    );
    $form['font']['stroke']['left'] = array(
      '#type' => 'number',
      '#title' => $this->t('Left'),
      '#default_value' => $this->configuration['font']['outline_left'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'outline'),
        ),
      ),
    );
    $form['font']['stroke']['x_offset'] = array(
      '#type' => 'number',
      '#title' => $this->t('Horizontal offset'),
      '#default_value' => $this->configuration['font']['shadow_x_offset'],
      '#maxlength' => 3,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 1,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'shadow'),
        ),
      ),
    );
    $form['font']['stroke']['y_offset'] = array(
      '#type' => 'number',
      '#title' => $this->t('Vertical offset'),
      '#default_value' => $this->configuration['font']['shadow_y_offset'],
      '#maxlength' => 3,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 1,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'shadow'),
        ),
      ),
    );
    $form['font']['stroke']['width'] = array(
      '#type' => 'number',
      '#title' => $this->t('Horizontal elongation'),
      '#default_value' => $this->configuration['font']['shadow_width'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'shadow'),
        ),
      ),
    );
    $form['font']['stroke']['height'] = array(
      '#type' => 'number',
      '#title' => $this->t('Vertical elongation'),
      '#default_value' => $this->configuration['font']['shadow_height'],
      '#maxlength' => 2,
      '#size' => 3,
      '#field_suffix' => 'px',
      '#min' => 0,
      '#states' => array(
        'visible' => array(
          ':radio[name="data[font][stroke][mode]"]' => array('value' => 'shadow'),
        ),
      ),
    );
    $form['font']['stroke']['color'] = array(
      '#type' => 'textimage_color',
      '#title' => $this->t('Color'),
      '#description'  => $this->t('Set the outline/shadow color.'),
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['font']['stroke_color'],
    );

    // ---- Text settings.
    $form['text'] = array(
      '#type'  => 'details',
      '#title' => $this->t('Text settings'),
      '#collapsed' => FALSE,
      '#group'   => 'settings',
    );
    // Inner width.
    $form['text']['maximum_width'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Maximum width'),
      '#field_suffix' => $this->t('px'),
      '#description' => $this->t('Maximum width of the text image, inclusive of padding. Text lines wider than this will be wrapped. Leave blank to disable wrapping. <b>Note:</b> in case of rotation, the width of the final image rendered will differ, to accomodate the rotation. If you need a strict width/height, add image resize/scale/crop effects afterwards.'),
      '#default_value' => $this->configuration['text']['maximum_width'],
      '#maxlength' => 4,
      '#size' => 4,
      '#min' => 0,
    );
    $form['text']['fixed_width'] = array(
      '#type'  => 'checkbox',
      '#title' => $this->t('Fixed width?'),
      '#description' => $this->t('If checked, the width will always be equal to the maximum width.'),
      '#default_value' => $this->configuration['text']['fixed_width'],
      '#states' => array(
        'visible' => array(
          ':input[name="data[text][maximum_width]"]' => array('value' => TRUE),
        ),
      ),
    );
    // Text alignment.
    $form['text']['align'] = array(
      '#type'  => 'select',
      '#title' => $this->t('Text alignment'),
      '#options' => array(
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ),
      '#default_value' => $this->configuration['text']['align'],
      '#description' => $this->t('Select how the text should be aligned within the resulting image. The default aligns to the left.'),
    );
    // Line spacing (Leading).
    $form['text']['line_spacing'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Line spacing (Leading)'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['text']['line_spacing'],
      '#maxlength' => 4,
      '#size' => 4,
      '#min' => 0,  // @todo can be negative??
      '#description' => $this->t('Specify the space in pixels to be added between text lines (Leading).'),
    );
    $form['text']['case_format'] = array(
      '#type'  => 'select',
      '#title' => $this->t('Case format'),
      '#options' => array(
        '' => $this->t('Default'),
        'upper' => $this->t('UPPERCASE'),
        'lower' => $this->t('lowercase'),
        'ucwords' => $this->t('Uppercase Words'),
        'ucfirst' => $this->t('Uppercase first'),
      ),
      '#description' => $this->t('Convert the input text to a desired format. The default makes no changes to input text.'),
      '#default_value' => $this->configuration['text']['case_format'],
    );

    // ---- Layout settings.
    $form['layout'] = array(
      '#type'  => 'details',
      '#title' => $this->t('Layout settings'),
      '#collapsed' => FALSE,
      '#group'   => 'settings',
    );
    // Position.
    $form['layout']['position'] = array(
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#title' => $this->t('Position'),
    );
    $form['layout']['position']['placement'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Placement'),
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
      '#default_value' => implode('-', array($this->configuration['layout']['x_pos'], $this->configuration['layout']['y_pos'])),
      '#description' => $this->t('Position of the text on the underlying image.'),
    );
    $form['layout']['position']['x_offset'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Horizontal offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional horizontal offset from placement.'),
      '#default_value' => $this->configuration['layout']['x_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    );
    $form['layout']['position']['y_offset'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Vertical offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional vertical offset from placement.'),
      '#default_value' => $this->configuration['layout']['y_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    );
    // Overflow action.
    $form['layout']['position']['overflow_action'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Overflow'),
      '#default_value' => $this->configuration['layout']['overflow_action'],
      '#options' => array(
        'extend' => $this->t('<b>Extend image.</b> The underlying image will be extended to fit the text.'),
        'crop' => $this->t('<b>Crop text.</b> Only the part of the text fitting in the image is rendered.'),
        'scaletext' => $this->t('<b>Scale text.</b> The text will be scaled to fit the underlying image.'),
      ),
      '#description' => $this->t('Action to take if text overflows the underlying image.'),
    );
    // Padding.
    $form['layout']['padding'] = array(
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#title' => $this->t('Padding'),
      '#description' => $this->t('Specify the padding in pixels to be added around the generated text.'),
    );
    $form['layout']['padding']['top'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Top'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_top'],
      '#maxlength' => 4,
      '#size' => 4,
    );
    $form['layout']['padding']['right'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Right'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_right'],
      '#maxlength' => 4,
      '#size' => 4,
    );
    $form['layout']['padding']['bottom'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Bottom'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_bottom'],
      '#maxlength' => 4,
      '#size' => 4,
    );
    $form['layout']['padding']['left'] = array(
      '#type'  => 'number',
      '#title' => $this->t('Left'),
      '#field_suffix'  => $this->t('px'),
      '#default_value' => $this->configuration['layout']['padding_left'],
      '#maxlength' => 4,
      '#size' => 4,
    );
    // Background color.
    $form['layout']['background_color'] = array(
      '#type' => 'textimage_color',
      '#title' => $this->t('Background color'),
      '#description'  => $this->t('Select the color you wish to use for the background of the text.'),
      '#allow_transparent' => TRUE,
      '#allow_opacity' => TRUE,
      '#default_value' => $this->configuration['layout']['background_color'],
    );

    $form['#attached']['css'] = array(
      drupal_get_path('module', 'textimage') . '/css/textimage.admin.css',
    );

    $form['#element_validate'][] = array($this, 'validateForm');

    return $form;
  }

  /**
   * AJAX callback.
   */
  public function processAjaxPreview($form, $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#textimage-preview', $this->previewImage($form_state['values']['data_back'])));
    return $response;
  }

  /**
   * Settings for 'textimage_text' image effect - form validation.
   */
  public function validateForm($element, &$form_state, $form) {
    $v = &$form_state['values']['data'];
$savex=$form_state['values']['data']['preview_bar']['debug_visuals'];
    // Get x-y position from the anchor element.
    list($v['layout']['position']['x_pos'], $v['layout']['position']['y_pos']) = explode('-', $v['layout']['position']['placement']);
    unset ($v['layout']['position']['placement']);

    // Get the font URI.
    $font_uri = !empty($v['font']['name']) ? $this->fontPlugin->getUri($v['font']['name']) : NULL;

    $v = array(
      'font'   => array(
        'name'                 => !empty($v['font']['name']) ? $v['font']['name'] : NULL,
        'uri'                  => $font_uri,
        'size'                 => $v['font']['size'],
        'angle'                => $v['font']['angle'],
        'color'                => $v['font']['color'],
        'stroke_mode'          => $v['font']['stroke']['mode'],
        'stroke_color'         => $v['font']['stroke']['color'],
        'outline_top'          => $v['font']['stroke']['top'],
        'outline_right'        => $v['font']['stroke']['right'],
        'outline_bottom'       => $v['font']['stroke']['bottom'],
        'outline_left'         => $v['font']['stroke']['left'],
        'shadow_x_offset'      => $v['font']['stroke']['x_offset'],
        'shadow_y_offset'      => $v['font']['stroke']['y_offset'],
        'shadow_width'         => $v['font']['stroke']['width'],
        'shadow_height'        => $v['font']['stroke']['height'],
      ),
      'layout' => array(
        'padding_top'          => $v['layout']['padding']['top'],
        'padding_right'        => $v['layout']['padding']['right'],
        'padding_bottom'       => $v['layout']['padding']['bottom'],
        'padding_left'         => $v['layout']['padding']['left'],
        'x_pos'                => $v['layout']['position']['x_pos'],
        'y_pos'                => $v['layout']['position']['y_pos'],
        'x_offset'             => $v['layout']['position']['x_offset'],
        'y_offset'             => $v['layout']['position']['y_offset'],
        'overflow_action'      => $v['layout']['position']['overflow_action'],
        'background_color'     => $v['layout']['background_color'],
      ),
      'text'   => array(
        'maximum_width'        => $v['text']['maximum_width'],
        'fixed_width'          => $v['text']['fixed_width'],
        'align'                => $v['text']['align'],
        'case_format'          => $v['text']['case_format'],
        'line_spacing'         => $v['text']['line_spacing'],
      ),
      'text_string'            => $v['text_default']['text_string'],
    );
    $form_state['values']['data_back'] = $form_state['values']['data'];
    $form_state['values']['data_back']['preview_bar']['debug_visuals'] = $savex;
  }

  /**
   * Deliver a preview of the textimage with the current settings.
   *
   * @param array $data
   *   The current configuration for this image effect.
   *
   * @return array
   *   The HTML to the preview image.
   */
  protected function previewImage($data) {
    $data['layout']['x_pos'] = 'center';
    $data['layout']['y_pos'] = 'center';
    $data['layout']['x_offset'] = 0;
    $data['layout']['y_offset'] = 0;
    $data['layout']['overflow_action'] = 'extend';
    $data['debug_visuals'] = $data['preview_bar']['debug_visuals'];
    return theme('textimage_formatter', array(
        'text' => array($data['text_string']),
        'effects' => array(
          array(
            'id' => 'textimage_text',
            'weight' => -5,  // @todo better
            'data' => $data,
          ),
        ),
        'title' => $this->t('Preview'),
        'alt' => $this->t('Display preview not available.'),
        'caching' => FALSE,
      )
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    $data['font_color_detail'] = array(
      '#theme' => 'textimage_color_detail',
      '#color' => $data['font']['color'],
      '#border' => TRUE,
      '#border_color' => 'matchLuma',
    );
    if ($stroke_mode = $this->strokeMode()) {
      $data['stroke_mode'] = ($stroke_mode == 'outline') ? $this->t('Outline') : $this->t('Shadow');
      $data['stroke_color_detail'] = array(
        '#theme' => 'textimage_color_detail',
        '#color' => $data['font']['stroke_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      );
    }
    if ($data['layout']['background_color']) {
      $data['background_color_detail'] = array(
        '#theme' => 'textimage_color_detail',
        '#color' => $data['layout']['background_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      );
    }
    return array(
      '#theme' => 'textimage_text_summary',
      '#data' => $data,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Get the text wrapper resource.
    if (!$wrapper = $this->getTextWrapper($image, $this->configuration)) {
      return FALSE;
    }

    // Background image dimensions.
    $image_width = $image->getWidth();
    $image_height = $image->getHeight();

    // Offset wrapper dimensions.
    $offset_wrapper = array();

    // Determine needed resizing/repositioning of background image and/or
    // text wrapper image, based on the settings.
    switch ($this->configuration['layout']['overflow_action']) {
      case 'extend':
        // The background image new dimensions, after extension.
        $image_new = array(
          'xpos' => 0,
          'ypos' => 0,
          'width' => $image_width,
          'height' => $image_height,
        );

        // The size of the frame sides for color filling.
        $frame = array(
          'top' => 0,
          'right' => 0,
          'bottom' => 0,
          'left' => 0,
        );

        // Check wrapper image overflowing the original image.
        list($resized, $offset_wrapper) = $this->backgroundImageResize($image, $wrapper, $this->configuration, $image_new, $frame);
        if ($resized) {
          // Call out to canvasactions_definecanvas_effect(), transparent
          // background.
          $canvas_data = array(
            'RGB' => array(
              'HEX' => NULL,
            ),
            'under' => TRUE,
            'exact' => $image_new,
          );
          if (!canvasactions_definecanvas_effect($image, $canvas_data)) {
            return FALSE;
          }
          // Color fill the frame with carried on background color.
          if ($main_bg_color = $this->textimageFactory->getState('background_color')) {
            // Top rectangle.
            if ($frame['top']) {
              $points = array(
                0, 0,
                $image_new['width'] - 1, 0,
                $image_new['width'] - 1, $frame['top'] - 1,
                0, $frame['top'] - 1,
              );
              static::drawRectangle($image, $points, $main_bg_color);
            }
            // Bottom rectangle.
            if ($frame['bottom']) {
              $points = array(
                0, $image_height + $frame['top'],
                $image_new['width'] - 1, $image_height + $frame['top'],
                $image_new['width'] - 1, $image_new['height'] - 1,
                0, $image_new['height'] - 1,
              );
              static::drawRectangle($image, $points, $main_bg_color);
            }
            // Left rectangle.
            if ($frame['left']) {
              $points = array(
                0, $frame['top'],
                $frame['left'] - 1, $frame['top'],
                $frame['left'] - 1, $frame['top'] + $image_height - 1,
                0, $frame['top'] + $image_height - 1,
              );
              static::drawRectangle($image, $points, $main_bg_color);
            }
            // Right rectangle.
            if ($frame['right']) {
              $points = array(
                $frame['left'] + $image_width, $frame['top'],
                $image_new['width'] - 1, $frame['top'],
                $image_new['width'] - 1, $frame['top'] + $image_height - 1,
                $frame['left'] + $image_width, $frame['top'] + $image_height - 1,
              );
              static::drawRectangle($image, $points, $main_bg_color);
            }
          }
        }
        break;

      case 'scaletext':
        // Check if scaling down is needed.
        list($resized, $offset_wrapper) = $this->wrapperResize($image, $wrapper, $this->configuration);
        if ($resized) {
          $scale_data = array(
            'data' => array(
              'width' => $offset_wrapper['width'],
              'height' => $offset_wrapper['height'],
              'upscale' => 0,
            ),
          );
          $effect = $this->effectsFactory->createInstance('image_scale', $scale_data); // @todo use toolkit call directly
          if (!$effect->applyEffect($wrapper)) {
            return FALSE;
          }
        }
        break;

      case 'crop':
      default:
        // Nothing to do, just place the wrapper at offset required.
        $x_offset = ceil(image_filter_keyword($this->configuration['layout']['x_pos'], $image_width, $wrapper->getWidth()));
        $y_offset = ceil(image_filter_keyword($this->configuration['layout']['y_pos'], $image_height, $wrapper->getHeight()));
        $offset_wrapper['xpos'] = $x_offset + $this->configuration['layout']['x_offset'];
        $offset_wrapper['ypos'] = $y_offset + $this->configuration['layout']['y_offset'];
        break;

    }

    // Finally, lay the wrapper over the source image.
    if (!empty($offset_wrapper)) {
      if (!image_overlay($image, $wrapper, $offset_wrapper['xpos'], $offset_wrapper['ypos'])) {
        return FALSE;
      }
    }

    // Reset transparency color for .gif format.
    if ($image->getMimeType() == 'image/gif') {
      _textimage_toolkit_invoke('textimage_set_transparency', $image, array($this->textimageFactory->getState('gif_transparency_color')));
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions) {
    // Dimensions are potentially affected only if the effect is set to
    // autoextend the background image in case of wrapper overflow.
    if ($this->configuration['layout']['overflow_action'] == 'extend') {

      // Dummy image object.
      $image = new stdClass();
      $image->info['width'] = $dimensions['width'];
      $image->info['height'] = $dimensions['height'];
      $image->info['extension'] = 'png';
      $image->info['mime_type'] = 'image/png';
      $image->toolkit = image_get_toolkit();

      // Get the text wrapper resource.
      if (!$wrapper = $this->getTextWrapper($image, $this->configuration)) {
        return;
      }

      // The background image new dimensions, after extension.
      $image_new = array(
        'xpos' => 0,
        'ypos' => 0,
        'width' => $image->info['width'],
        'height' => $image->info['height'],
      );

      // Checks if resizing needed.
      list($resized, $offset_wrapper) = $this->backgroundImageResize($image, $wrapper, $this->configuration, $image_new);
      if ($resized) {
        $dimensions['width'] = $image_new['width'];
        $dimensions['height'] = $image_new['height'];
      }

    }
  }

  /**
   * Get the image containing the text.
   *
   * This is separated from textimage_text_effect() so that it can also be used
   * by the textimage_text_effect_dimensions() function.
   */
  protected function getTextWrapper($image, array $data) {

    // Check font path.
    $font_uri = $data['font']['uri'];
    $data['font']['uri'] = static::getFontPath($image, $data['font']['uri']);
    if (!$data['font']['uri']) {
      _textimage_diag(
        $this->t(
          "Textimage could not find the font file @fontfile for font @fontname",
          array(
            '@fontfile' => $font_uri,
            '@fontname' => $data['font']['name'],
          )
        ),
        WATCHDOG_ERROR,
        __FUNCTION__);
      return NULL;
    }

    // If the effect is executed outside of the context of the TextimageStyle
    // class (e.g. by the core Image module), then the text_string has not been
    // pre-processed to translate tokens or apply text conversion.
    if (!($this->textimageFactory->getState('building_module') == 'textimage')) {
      $data['text_string'] = $this->textimageFactory->processTextString($data['text_string'], $data['text']['case_format']);
    }

    // Determine if outline/shadow is required.
    $outline = $shadow = FALSE;
    if ($data['font']['stroke_mode'] == 'outline' && ($data['font']['outline_top'] || $data['font']['outline_right'] || $data['font']['outline_bottom'] || $data['font']['outline_left']) && $data['font']['stroke_color']) {
      $outline = TRUE;
    }
    elseif ($data['font']['stroke_mode'] == 'shadow' && ($data['font']['shadow_x_offset'] || $data['font']['shadow_y_offset'] || $data['font']['shadow_width'] || $data['font']['shadow_height']) && $data['font']['stroke_color']) {
      $shadow = TRUE;
    }

    // Add stroke to padding to ensure inner box includes entire font space.
    if ($outline) {
      $data['layout']['padding_top'] += $data['font']['outline_top'];
      $data['layout']['padding_right'] += $data['font']['outline_right'];
      $data['layout']['padding_bottom'] += $data['font']['outline_bottom'];
      $data['layout']['padding_left'] += $data['font']['outline_left'];
    }
    elseif ($shadow) {
      $data['layout']['padding_top'] += ($data['font']['shadow_y_offset'] < 0 ? -$data['font']['shadow_y_offset'] : 0);
      $data['layout']['padding_right'] += ($data['font']['shadow_x_offset'] > 0 ? $data['font']['shadow_x_offset'] : 0);
      $data['layout']['padding_bottom'] += ($data['font']['shadow_y_offset'] > 0 ? $data['font']['shadow_y_offset'] : 0);
      $data['layout']['padding_left'] += ($data['font']['shadow_x_offset'] < 0 ? -$data['font']['shadow_x_offset'] : 0);
      $shadow_width = ($data['font']['shadow_x_offset'] != 0) ? $data['font']['shadow_width'] + 1 : $data['font']['shadow_width'];
      $shadow_height = ($data['font']['shadow_y_offset'] != 0) ? $data['font']['shadow_height'] + 1 : $data['font']['shadow_height'];
      $net_right = $shadow_width + ($data['font']['shadow_x_offset'] >= 0 ? 0 : $data['font']['shadow_x_offset']);
      $data['layout']['padding_right'] += ($net_right > 0 ? $net_right : 0);
      $net_bottom = $shadow_height + ($data['font']['shadow_y_offset'] >= 0 ? 0 : $data['font']['shadow_y_offset']);
      $data['layout']['padding_bottom'] += ($net_bottom > 0 ? $net_bottom : 0);
    }

    // Perform text wrapping, if necessary.
    if ($data['text']['maximum_width'] > 0) {
      $data['text_string'] = static::wrapText(
        $image,
        $data['text_string'],
        $data['font']['size'],
        $data['font']['uri'],
        $data['text']['maximum_width'] - $data['layout']['padding_left'] - $data['layout']['padding_right'] - 1,
        $data['text']['align']
      );
    }

    // Load text lines to array elements.
    $text_lines = explode("\n", $data['text_string']);
    $num_lines = count($text_lines);

    // Calculate bounding boxes.
    // ---------------------------------------
    // Inner box   - the exact bounding box of the text.
    // Outer box   - the box where the inner box is - can be different because
    //               of padding.
    // Wrapper     - the canvass where the outer box is laid.
    // ---------------------------------------

    // Get inner box, for horizontal text, unpadded.
    $inner_box = static::getBoundingBox($image, $data['text_string'], $num_lines, $data['font']['size'], $data['font']['uri']);

    // Adjust to fixed width, if requested.
    if ($data['text']['fixed_width'] && !empty($data['text']['maximum_width'])) {
      $inner_box->set('width', $data['text']['maximum_width'] - $data['layout']['padding_left'] - $data['layout']['padding_right']);
    }

    // Determine average text line height.
    $line_height = round($inner_box->get('height') / $num_lines);

    // Manage leading (line spacing), adding total line spacing to height.
    if ($data['text']['line_spacing']) {
      $inner_box->set('height', $inner_box->get('height') + ($data['text']['line_spacing'] * ($num_lines - 1)));
    }

    // Apply padding to get outer box.
    $outer_box = clone $inner_box;
    $outer_box->set('width', $outer_box->get('width') + $data['layout']['padding_right'] + $data['layout']['padding_left']);
    $outer_box->set('height', $outer_box->get('height') + $data['layout']['padding_top'] + $data['layout']['padding_bottom']);

    // Get details for the rotated/translated boxes.
    $outer_box_t = $outer_box->getTranslatedBox(
      $data['font']['angle']
    );
    $inner_box_t = $inner_box->getTranslatedBox(
      $data['font']['angle'],
      array(
        $data['layout']['padding_left'],
        $data['layout']['padding_top'],
      ),
      $outer_box_t->get('topLeftCornerPosition')
    );

    // Create the wrapper image object as a canvass for the text.
    $wrapper = clone $image;
    $wrapper->setWidth($outer_box_t->get('width'));
    $wrapper->setHeight($outer_box_t->get('height'));

    // Calls image generation for the wrapper image.
    $data_textimage = array(
      'font' => $data['font'],
      'layout' => $data['layout'],
      'text' => $data['text'],
      'text_lines' => $text_lines,
      'inner_width' => $inner_box->get('width'),
      'inner_height' => $inner_box->get('height'),
      'inner_basepoint' => $inner_box->get('basepoint'),
      'topLeftCornerPosition' => $outer_box_t->get('topLeftCornerPosition'),
      'inner_box' => $inner_box_t->get('points'),
      'outer_box' => $outer_box_t->get('points'),
      'line_height' => $line_height,
      'debug_visuals' => isset($data['debug_visuals']) ? $data['debug_visuals'] : FALSE,
      'gif_transparency_color' => $this->textimageFactory->getState('gif_transparency_color'),
    );
    if (!_textimage_toolkit_invoke('textimage_text_to_image', $wrapper, array($data_textimage))) {
      return NULL;
    }
    return $wrapper;
  }

  /**
   * Recalculate background image size.
   *
   * When wrapper overflows the original image, and autoextent is set on.
   */
  protected function backgroundImageResize($image, $wrapper, $data, &$image_new, &$frame = NULL) {

    $resized = FALSE;

    // Background image dimensions.
    $image_width = $image->getWidth();
    $image_height = $image->getHeight();

    // Wrapper image dimensions.
    $wrapper_width = $wrapper->getWidth();
    $wrapper_height = $wrapper->getHeight();

    // Determine wrapper offset, based on placement option.
    // This is just taking into account the image and wrapper dimensions;
    // additional offset explicitly specified is considered later.
    $x_offset = ceil(image_filter_keyword($data['layout']['x_pos'], $image_width, $wrapper_width));
    $y_offset = ceil(image_filter_keyword($data['layout']['y_pos'], $image_height, $wrapper_height));

    // The position of the wrapper, once offset as per explicit
    // input. Width and height are not relevant for the algorithm,
    // but would be determined as follows:
    //  'width' => ($wrapper_width < $image_width) ? $wrapper_width + abs($data['layout']['x_offset']) : $wrapper_width;
    //  'height' = ($wrapper_height < $image_height) ? $wrapper_height + abs($data['layout']['y_offset']) : $wrapper_height;
    $offset_wrapper = array(
      'xpos' => $x_offset + $data['layout']['x_offset'],
      'ypos' => $y_offset + $data['layout']['y_offset'],
    );

    // If offset wrapper overflows to the left, background image
    // will be shifted to the right.
    if ($offset_wrapper['xpos'] < 0) {
      $image_new['width'] = $image_width - $offset_wrapper['xpos'];
      $image_new['xpos'] = -$offset_wrapper['xpos'];
      $offset_wrapper['xpos'] = 0;
      if (isset($frame)) {
        $frame['left'] = $image_new['width'] - $image_width;
      }
      $resized = TRUE;
    }

    // If offset wrapper overflows to the top, background image
    // will be shifted to the bottom.
    if ($offset_wrapper['ypos'] < 0) {
      $image_new['height'] = $image_height - $offset_wrapper['ypos'];
      $image_new['ypos'] = -$offset_wrapper['ypos'];
      $offset_wrapper['ypos'] = 0;
      if (isset($frame)) {
        $frame['top'] = $image_new['height'] - $image_height;
      }
      $resized = TRUE;
    }

    // If offset wrapper overflows to the right, background image
    // will be extended to the right.
    if (($offset_wrapper['xpos'] + $wrapper_width) > $image_new['width']) {
      $tmp = $image_new['width'];
      $image_new['width'] = $offset_wrapper['xpos'] + $wrapper_width;
      if (isset($frame)) {
        $frame['right'] = $image_new['width'] - $tmp;
      }
      $resized = TRUE;
    }

    // If offset wrapper overflows to the bottom, background image
    // will be extended to the bottom.
    if (($offset_wrapper['ypos'] + $wrapper_height) > $image_new['height']) {
      $tmp = $image_new['height'];
      $image_new['height'] = $offset_wrapper['ypos'] + $wrapper_height;
      if (isset($frame)) {
        $frame['bottom'] = $image_new['height'] - $tmp;
      }
      $resized = TRUE;
    }

    return array($resized, $offset_wrapper);
  }

  /**
   * Recalculate wrapper image size.
   *
   * When wrapper overflows the original image, and scaling is set on.
   */
  protected function wrapperResize($image, $wrapper, $data) {

    $resized = FALSE;

    // Background image dimensions.
    $image_width = $image->getWidth();
    $image_height = $image->getHeight();

    // Wrapper image dimensions.
    $wrapper_width = $wrapper->getWidth();
    $wrapper_height = $wrapper->getHeight();

    // Determine wrapper offset, based on placement option and direct
    // offset indicated in settings.
    $offset_wrapper = array(
      'xpos' => ceil(image_filter_keyword($data['layout']['x_pos'], $image_width, $wrapper_width)) + $data['layout']['x_offset'],
      'ypos' => ceil(image_filter_keyword($data['layout']['y_pos'], $image_height, $wrapper_height)) + $data['layout']['y_offset'],
    );

    // Position of wrapper's bottom right point.
    $xc_pos = $offset_wrapper['xpos'] + $wrapper_width;
    $yc_pos = $offset_wrapper['ypos'] + $wrapper_height;

    // Redetermine offset wrapper position and size based on
    // background image size.
    $offset_wrapper['xpos'] = max(0, $offset_wrapper['xpos']);
    $offset_wrapper['ypos'] = max(0, $offset_wrapper['ypos']);
    $xc_pos = min($image_width, $xc_pos);
    $yc_pos = min($image_height, $yc_pos);
    $offset_wrapper['width'] = $xc_pos - $offset_wrapper['xpos'];
    $offset_wrapper['height'] = $yc_pos - $offset_wrapper['ypos'];

    // If negative width/height, then the wrapper is totally
    // overflowing the background, and we cannot resize it.
    if ($offset_wrapper['width'] < 0 || $offset_wrapper['height'] < 0) {
      return array(FALSE, array());
    }

    // Determine if scaling needed. Take the side that is shrinking
    // most.
    $width_resize_index = $offset_wrapper['width'] / $wrapper_width;
    $height_resize_index = $offset_wrapper['height'] / $wrapper_height;
    if ($width_resize_index < 1 || $height_resize_index < 1) {
      $resized = TRUE;
      if ($width_resize_index < $height_resize_index) {
        $offset_wrapper['height'] = NULL;
      }
      else {
        $offset_wrapper['width'] = NULL;
      }
    }

    return array($resized, $offset_wrapper);
  }

  /**
   * Helpers
   */

  /**
   * @todo
   */
  protected function strokeMode() {
    if ($this->configuration['font']['stroke_mode'] == 'outline' && ($this->configuration['font']['outline_top'] || $this->configuration['font']['outline_right'] || $this->configuration['font']['outline_bottom'] || $this->configuration['font']['outline_left'])) {
      return 'outline';
    }
    else if ($this->configuration['font']['stroke_mode'] == 'shadow') {
      return 'shadow';
    }
    else {
      return NULL;
    }
  }

  /**
   * Wrap text for rendering at a given width.
   *
   * @param object $image
   *   Image object.
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
  public static function wrapText($image, $text, $font_size, $font_uri, $maximum_width) {
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
      $width = static::measureTextWidth($image, $line, 1, $font_size, $font_uri);

      // See if line extends past the available space.
      if ($width > $maximum_width) {
        // If this is the first word, we need to truncate it.
        if ($fit == $begin) {
          // Cut off letters until it fits.
          while (Unicode::strlen($line) > 0 && $width > $maximum_width) {
            $line  = Unicode::substr($line, 0, -1);
            $width = static::measureTextWidth($image, $line, 1, $font_size, $font_uri);
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
   * Return the path of the font file, in a format usable by current toolkit.
   *
   * @param object $image
   *   Image object.
   * @param string $font_uri
   *   URI of the TrueType font to use.
   *
   * @return string
   *   Fully qualified font file path.
   */
  protected static function getFontPath($image, $font_uri) {
    $data = array(
      'font_uri' => $font_uri,
    );
    return _textimage_toolkit_invoke('textimage_get_font_path', $image, array($data));
  }

  /**
   * Return a text bounding box.
   *
   * @param object $image
   *   Image object.
   * @param string $text
   *   Text string in UTF-8 encoding.
   * @param int $lines
   *   The number of lines the text is composed of.
   * @param int $font_size
   *   Font size.
   * @param string $font_uri
   *   URI of the TrueType font to use.
   * @param float $angle
   *   Text rotation angle.
   *
   * @return TextimageTextbox
   *   Textbox object.
   */
  public static function getBoundingBox($image, $text, $lines, $font_size, $font_uri, $angle = 0) {
    $data = array(
      'size' => $font_size,
      'angle' => $angle,
      'fontfile' => $font_uri,
      'text' => $text,
      'lines' => $lines,
    );
    return _textimage_toolkit_invoke('textimage_get_bounding_box', $image, array($data));
  }

  /**
   * Measure text box width.
   *
   * @param object $image
   *   Image object.
   * @param string $text
   *   Text string in UTF-8 encoding.
   * @param int $lines
   *   The number of lines the text is composed of.
   * @param int $font_size
   *   Font size.
   * @param string $font_uri
   *   URI of the TrueType font to use.
   *
   * @return array
   *   An associative array of box measurements.
   */
  protected static function measureTextWidth($image, $text, $lines, $font_size, $font_uri) {
    $box = static::getBoundingBox($image, $text, $lines, $font_size, $font_uri);
    return $box->get('width');
  }

  /**
   * Draw a rectangle.
   *
   * @param object $image
   *   Image object.
   * @param array $points
   *   Box points coordinates array.
   * @param string $rgba
   *   RGBA color of the rectangle.
   * @param bool $luma
   *   if TRUE, convert RGBA to best match using luma.
   */
  public static function drawRectangle($image, $points, $rgba, $luma = FALSE) {

    // Check color.
    if ($rgba && $luma) {
      $rgba = ColorUtility::matchLuma($rgba);
    }

    // Invoke toolkit.
    $data = array(
      'points' => $points,
      'num_points' => 4,
      'border_color' => NULL,
      'fill_color' => $rgba,
    );
    return _textimage_toolkit_invoke('textimage_draw_polygon', $image, array($data));
  }

  /**
   * Draw a box.
   *
   * @param object $image
   *   Image object.
   * @param array $points
   *   Box points coordinates array.
   * @param string $rgba
   *   RGBA color of the rectangle.
   * @param bool $luma
   *   if TRUE, convert RGBA to best match using luma.
   */
  public static function drawBox($image, $points, $rgba, $luma = FALSE) {

    // Check color.
    if (!$rgba) {
      $rgba = '#00000000';
    }
    elseif ($luma) {
      $rgba = ColorUtility::matchLuma($rgba);
    }

    // Invoke toolkit.
    $data = array(
      'points' => $points,
      'num_points' => 4,
      'border_color' => $rgba,
      'fill_color' => NULL,
    );
    return _textimage_toolkit_invoke('textimage_draw_polygon', $image, array($data));
  }

  /**
   * Display a polygon enclosing the text line, and conspicuous points.
   *
   * Credit to Ruquay K Calloway
   *
   * @param object $image
   *   Image object.
   * @param TextimageTextbox $box
   *   Textbox object to draw (inclusing basepoint).
   * @param string $rgba
   *   RGBA color of the rectangle.
   * @param bool $luma
   *   if TRUE, convert RGBA to best match using luma.
   *
   * @see http://ruquay.com/sandbox/imagettf
   */
  public static function drawDebugBox($image, BoundingBox $box, $rgba, $luma = FALSE) {

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
    static::drawBox($image, $points, $rgba);

    // Draw diagonal.
    $data = array(
      'a' => array($points[0], $points[1]),
      'b' => array($points[4], $points[5]),
      'rgba' => $rgba,
    );
    _textimage_toolkit_invoke('textimage_draw_line', $image, array($data));

    // Conspicuous points.
    $orange = '#FF640000';
    $yellow = '#FFFF0000';
    $green  = '#00FF0000';
    $dotsize = 6;

    // Box corners.
    for ($i = 0; $i < 8; $i += 2) {
      $col = $i < 4 ? $orange : $yellow;
      $data = array(
        'c' => array($points[$i], $points[$i + 1]),
        'width' => $dotsize,
        'height' => $dotsize,
        'rgba' => $col,
      );
      _textimage_toolkit_invoke('textimage_draw_ellipse', $image, array($data));
    }

    // Font baseline.
    $data = array(
      'c' => $box->get('basepoint'),
      'width' => $dotsize,
      'height' => $dotsize,
      'rgba' => $green,
    );
    _textimage_toolkit_invoke('textimage_draw_ellipse', $image, array($data));
  }
}
