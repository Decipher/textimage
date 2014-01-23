<?php

namespace Drupal\textimage\Plugin;

use Drupal\Component\Plugin\ConfigurablePluginInterface;

/**
 * Textimage base plugin interface.
 */
interface TextimagePluginBaseInterface extends ConfigurablePluginInterface {

  /**
   * Return a form array used to configure the plugin.
   *
   * @return array
   *   Render array of the configuration form.
   */
  public function configurationForm(array $form, array &$form_state, array $options = array());

  /**
   * Return a form element to select the plugin content.
   *
   * @return array
   *   Render array of the form element.
   */
  public function selectionElement($name, array $options = array());

  /**
   * Get the plugin Textimage type.
   *
   * @return string
   *   The plugin type.
   */
  public function getType();

  /**
   * Determines if plugin can be used.
   *
   * @return boolean
   *   TRUE if the plugin is available.
   */
  public static function isAvailable();
}
