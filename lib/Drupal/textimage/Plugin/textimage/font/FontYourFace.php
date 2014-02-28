<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\textimage\font\FontYourFace.
 */

// @todo use of '@' in annotations with doctrine gives errors

namespace Drupal\textimage\Plugin\textimage\font;


/**
 * Fonts handler for font-your-face.
 *
 * Currently only provides access to fonts uploaded locally via the
 * local font uploading features.
 *
 * @Plugin(
 *   id = "fontyourface",
 *   title = @Translation("Fonts handler for @font-your-face."),
 *   short_title = @Translation("@font-your-face"),
 *   help = @Translation("Access fonts uploaded via @font-your-face 'local font' module.")
 * )
 */
class FontYourFace extends Textimage {

  /**
   * {@inheritdoc}
   */
  public function configurationForm(array $form, array &$form_state, array $options = array()) {
    // No configuration needed.
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return FALSE; // @todo check module!!
  }

  /**
   * Return an array of font names.
   *
   * @return array
   *   Array of font names.
   */
  protected function getList() {
    return array(); // @todo temp
    $list = array();
    $font_list = fontyourface_get_fonts($where = "enabled = 1 and provider = 'local_fonts'", $order_by = 'name ASC');
    if ($font_list) {
      foreach ($font_list as $font) {
        $list[] = $font->name;
      }
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function getUri($font_name) {
    return NULL; // @todo temp
    $font = fontyourface_get_fonts($where = "enabled = 1 and provider = 'local_fonts' and name = '$font_name'");
    if (!empty($font)) {
      $font = array_shift($font);
      $metadata = unserialize($font->metadata);
      if (isset($metadata['font_uri']['truetype'])) {
        return $metadata['font_uri']['truetype'];
      }
    }
    return NULL;
  }

}
