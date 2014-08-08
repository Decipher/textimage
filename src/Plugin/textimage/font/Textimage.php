<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\textimage\font\Textimage.
 */

namespace Drupal\textimage\Plugin\textimage\font;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\textimage\Plugin\TextimageFontPluginInterface;
use Drupal\textimage\Plugin\TextimagePluginBase;

/**
 * Basic font handler for Textimage.
 *
 * Provides access to fonts stored in a directory, specified in configuration.
 *
 * @Plugin(
 *   id = "textimage",
 *   title = @Translation("Textimage basic font handler"),
 *   short_title = @Translation("Textimage"),
 *   help = @Translation("Access fonts stored in the directory specified in configuration.")
 * )
 */
class Textimage extends TextimagePluginBase implements TextimageFontPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array('path' => 'private://textimage_store/fonts');
  }

  /**
   * {@inheritdoc}
   */
  public function configurationForm(array $form, FormStateInterface $form_state, array $options = array()) {
    $element['path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#default_value' => $this->configuration['path'],
      '#maxlength' => 255,
      '#element_validate' => array(array($this, 'validatePath')),
      '#limit_validation_errors' => FALSE,
      '#description' =>
        $this->t('Location of the directory where the fonts are stored.') . ' ' .
        $this->t('Relative paths will be resolved relative to the Drupal installation directory.'),
    );
    if (isset($options['#ajax'])) {
      $element['path']['#ajax'] = $options['#ajax'];
    }
    return $element;
  }

  /**
   * @todo
   */
  public function validatePath($element, FormStateInterface $form_state, $form) {
    if (!is_dir($element['#value'])) {
      form_set_error(implode('][', $element['#parents']), $form_state, $this->t('Invalid directory specified.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectionElement($name, array $options = array()) {

    // Get list of font names.
    $fonts_list = $this->getList();
    if (empty($fonts_list)) {
      _textimage_diag(
        $this->t(
          'No fonts available. Make sure at least one font is available in the directory specified in the <a href="!url">configuration page</a>.',
          array(
            '!url' => url('admin/config/media/textimage'),
          )
        ),
        WATCHDOG_WARNING
      );
      return array();
    }

    // Default font.
    $font_options = array_combine($fonts_list, $fonts_list);
    $default_font = $this->config->get('default_font.name');
    $default_value = array_key_exists($default_font, $font_options) ? $default_font : NULL;

    // Element.
    $element[$name] = array(
      '#type'    => 'select',
      '#title'   => isset($options['#title']) ? $options['#title'] : $this->t('Font'),
      '#description' => isset($options['#description']) ? $options['#description'] : $this->t('Select font.'),
      '#options' => $font_options,
      '#default_value' => $default_value,
      '#limit_validation_errors' => FALSE,
      '#required' => TRUE,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri($font_name) {
    if (is_dir($this->configuration['path']) && $handle = opendir($this->configuration['path'])) {
      while ($file_name = readdir($handle)) {
        if (preg_match("/\.[ot]tf$/i", $file_name) == 1) {
          $font = static::getData($this->configuration['path'] . '/' . $file_name);
          if ($font_name == $font['name']) {
            return $font['file'];
          }
        }
      }
      closedir($handle);
    }
    return NULL;
  }

  /**
   * Return an array of fonts.
   *
   * Scans through files available in the directory specified through
   * configuration.
   *
   * @param array $options
   *   an array of additional options.
   *
   * @return array
   *   Array of font names.
   */
  protected function getList() {
    $filelist = array();
    if (is_dir($this->configuration['path']) && $handle = opendir($this->configuration['path'])) {
      while ($file_name = readdir($handle)) {
        if (preg_match("/\.[ot]tf$/i", $file_name) == 1) {
          $font = static::getData($this->configuration['path'] . '/' . $file_name);
          $filelist[$file_name] = $font['name'];
        }
      }
      closedir($handle);
    }
    asort($filelist);
    return $filelist;
  }

  /**
   * Return the font information.
   *
   * Scans the font file to return tags information.
   *
   * @param string $uri
   *   the URI of the font file.
   *
   * @return array
   *   an associative array with the following keys:
   *   'copyright' => Copyright information
   *   'family' => Font family
   *   'subfamily' => Font subfamily
   *   'name' => Font name
   *   'file' => Font file URI
   */
  protected static function getData($uri) {
    $realpath = drupal_realpath($uri);
    $pathinfo = pathinfo($realpath);
    $fd = fopen($realpath, "r");
    $text = fread($fd, filesize($realpath));
    fclose($fd);

    $number_of_tabs = static::dec2hex(ord($text[4])) . static::dec2hex(ord($text[5]));
    for ($i = 0; $i < hexdec($number_of_tabs); $i++) {
      $tag = $text[12 + $i * 16] . $text[12 + $i * 16 + 1] . $text[12 + $i * 16 + 2] . $text[12 + $i * 16 + 3];
      if ($tag == "name") {
        $offset_name_table_hex = static::dec2hex(ord($text[12 + $i * 16 + 8])) . static::dec2hex(ord($text[12 + $i * 16 + 8 + 1])) . static::dec2hex(ord($text[12 + $i * 16 + 8 + 2])) . static::dec2hex(ord($text[12 + $i * 16 + 8 + 3]));
        $offset_name_table_dec = hexdec($offset_name_table_hex);
        $offset_storage_hex = static::dec2hex(ord($text[$offset_name_table_dec + 4])) . static::dec2hex(ord($text[$offset_name_table_dec + 5]));
        $offset_storage_dec = hexdec($offset_storage_hex);
        $number_name_records_hex = static::dec2hex(ord($text[$offset_name_table_dec + 2])) . static::dec2hex(ord($text[$offset_name_table_dec + 3]));
        $number_name_records_dec = hexdec($number_name_records_hex);
        break;
      }
    }

    $storage_dec = $offset_storage_dec + $offset_name_table_dec;
    $storage_hex = Unicode::strtoupper(dechex($storage_dec));
    $font = array(
      'copyright' => '',
      'family' => '',
      'subfamily' => '',
      'name' => '',
      'file' => $uri,
    );

    for ($j = 0; $j < $number_name_records_dec; $j++) {
      $platform_id_hex = static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 0])) . static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 1]));
      $platform_id_dec = hexdec($platform_id_hex);
      $name_id_hex = static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 6])) . static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 7]));
      $name_id_dec = hexdec($name_id_hex);
      $string_length_hex = static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 8])) . static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 9]));
      $string_length_dec = hexdec($string_length_hex);
      $string_offset_hex = static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 10])) . static::dec2hex(ord($text[$offset_name_table_dec + 6 + $j * 12 + 11]));
      $string_offset_dec = hexdec($string_offset_hex);

      if ($name_id_dec == 0 && empty($font['copyright'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['copyright'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($name_id_dec == 1 && empty($font['family'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['family'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($name_id_dec == 2 && empty($font['subfamily'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['subfamily'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($name_id_dec == 4 && empty($font['name'])) {
        for ($l = 0; $l < $string_length_dec; $l++) {
          if (ord($text[$storage_dec + $string_offset_dec + $l]) >= 32) {
            $font['name'] .= $text[$storage_dec + $string_offset_dec + $l];
          }
        }
      }

      if ($font['copyright'] != "" && $font['family'] != "" && $font['subfamily'] != "" && $font['name'] != "") {
        break;
      }
    }

    return $font;
  }

  /**
   * Convert a dec to a hex.
   *
   * @param int $dec
   *   an integer number
   *
   * @return string
   *   the number represented as hex
   */
  protected static function dec2hex($dec) {
    $hex = dechex($dec);
    return str_repeat("0", 2 - Unicode::strlen($hex)) . Unicode::strtoupper($hex);
  }
}
