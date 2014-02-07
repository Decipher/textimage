<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\textimage\color\JQueryColorpicker.
 */

namespace Drupal\textimage\Plugin\textimage\color;

use Drupal\Component\Utility\Unicode;
use Drupal\textimage\Plugin\TextimageColorPluginInterface;
use Drupal\textimage\Plugin\TextimagePluginBase;

/**
 * Basic color plugin for Textimage.
 *
 * Provides access to images stored in a directory, specified in configuration.
 *
 * @Plugin(
 *   id = "jquery_colorpicker",
 *   title = @Translation("jQuery Colorpicker color handler"),
 *   short_title = @Translation("jQuery Colorpicker"),
 *   help = @Translation("Use jQuery Colorpicker to select colors.")
 * )
 */
class JQueryColorpicker extends TextimagePluginBase implements TextimageColorPluginInterface {

  public function selectionElement($name, array $options = array()) {
    $element[$name] = array(
      '#title' => t('Color'),
      '#type' => 'jquery_colorpicker',
      '#default_value' => Unicode::substr($options['#default_value'], -6),
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return FALSE; // @todo check module!!
    $color_selector_options = array();
    $color_selector_options['textbox'] = $this->t('Textbox');
    if (_textimage_module_exists('jquery_colorpicker', TEXTIMAGE_JQUERY_COLORPICKER_MIN_VERSION)) {
      $color_selector_options['jquery_colorpicker'] = $this->t('jQuery Colorpicker');
    }
  }

}
