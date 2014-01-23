<?php

namespace Drupal\textimage\Plugin;

/**
 * Fonts handler interface.
 *
 * Defines the methods that font plugins have to implement.
 */
interface TextimageFontPluginInterface extends TextimagePluginBaseInterface {

  /**
   * Get the URI of a font file.
   *
   * @param string $font_name
   *   the name of the font.
   *
   * @return string
   *   the URI of the font file.
   */
  public function getUri($font_name);

}
