<?php

/**
 * @file
 * Contains \Drupal\textimage\Element\TextimageColor.
 */

namespace Drupal\textimage\Element;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\textimage\Component\ColorUtility;

/**
 * Implements a form element to enable capturing color information.
 *
 * Enable capturing color information. Depending on the options, uses a normal
 * textfield or a jquery_colorpicker element to capture the HEX value of a
 * color.
 *
 * @FormElement("textimage_color")
 */
class TextimageColor extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#process' => array(
        array($class, 'processTextimageColor'),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // Normalize returned element values to a rgba hex value.
      $val = NULL;
      if ($element['#allow_transparent'] && !empty($input['container']['transparent'])) {
        return NULL;
      }
      elseif ($element['#allow_transparent'] || $element['#allow_opacity']) {
        $val = Unicode::strtoupper($input['container']['hex']);
      }
      else {
        $val = Unicode::strtoupper($input['hex']);
      }
      if ($val[0] <> '#') {
        $val = '#' . $val;
      }
      if ($element['#allow_opacity']) {
        $val .= ColorUtility::opacityToAlpha($input['container']['opacity']);
      }
      return $val;
    }
    return NULL;
  }

  /**
   * Processes a 'textimage_color' form element.
   *
   * @param array $element
   *   The form element to process. Properties used:
   *     '#allow_transparent' - if set to TRUE, a checkbox is displayed to set the
   *      color as a full transparency, In this case, color hex and opacity are
   *      hidden, and the value returned is NULL.
   *     '#allow_opacity' - if set to TRUE, a textfield is displayed to capture the
   *      'opacity' value, as a percentage.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processTextimageColor(&$element, FormStateInterface $form_state, &$complete_form) {
    // Make sure element properties are set.
    $element['#allow_transparent'] = isset($element['#allow_transparent']) ? $element['#allow_transparent'] : FALSE;
    $element['#allow_opacity'] = isset($element['#allow_opacity']) ? $element['#allow_opacity'] : FALSE;
    $element['#description'] = isset($element['#description']) ? $element['#description'] : NULL;

    // In case default value is transparent, set hex and opacity to default
    // values (white, fully opaque) so that if transparency is unchecked,
    // we have a starting value.
    $transparent = empty($element['#default_value']) ? TRUE : FALSE;
    $hex = $transparent ? '#FFFFFF' : Unicode::substr($element['#default_value'], 0, 7);
    $opacity = $transparent ? 100 : ColorUtility::rgbaToOpacity($element['#default_value']);

    $colorPlugin = \Drupal::service('plugin.manager.textimage.color')->getPlugin(); // @todo inject?

    if ($element['#allow_transparent'] || $element['#allow_opacity']) {
      // More sub-fields are needed to define the color, wrap them in a
      // container fieldset.
      $element['container'] = array(
        '#type' => 'fieldset',
        '#description' => $element['#description'],
        '#title' => $element['#title'],
      );
      // Checkbox for transparency.
      if ($element['#allow_transparent']) {
        $element['container']['transparent'] = array(
          '#type' => 'checkbox',
          '#title' => t('Transparent'),
          '#default_value' => $transparent,
        );
      }
      // Color field.
      $element['container'] += $colorPlugin->selectionElement('hex', array('#default_value' => $hex));
      // States management for color field.
      $element['container']['hex']['#states'] = array(
        'visible' => array(
          ':input[name="' . $element['#name'] . '[container][transparent]"]' => array('checked' => FALSE),
        ),
      );
      // Textfield for opacity.
      if ($element['#allow_opacity']) {
        $element['container']['opacity'] = array(
          '#type'  => 'number',
          '#title' => t('Opacity'),
          '#default_value' => $opacity,
          '#maxlength' => 3,
          '#size' => 2,
          '#field_suffix' => '%',
          '#min' => 0,
          '#max' => 100,
          '#states' => array(
            'visible' => array(
              ':input[name="' . $element['#name'] . '[container][transparent]"]' => array('checked' => FALSE),
            ),
          ),
        );
      }
    }
    else {
      // No transparency or opacity, straight color field.
      $element += $colorPlugin->selectionElement('hex', array('#default_value' => $hex));
    }

    unset(
      $element['#description'],
      $element['#title']
    );

    return $element;
  }

}
