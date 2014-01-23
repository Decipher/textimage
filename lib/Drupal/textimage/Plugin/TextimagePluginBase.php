<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\TextimagePluginBase.
 */

namespace Drupal\textimage\Plugin;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base plugin for Textimage.
 */
class TextimagePluginBase extends PluginBase implements TextimagePluginBaseInterface {

  protected $pluginType;

  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginType = $configuration['plugin_type'];
    $config = config('textimage.settings')->get($this->pluginType . '.plugin_settings.' . $plugin_id);
    $this->setConfiguration(array_merge($this->defaultConfiguration(), is_array($config) ? $config : array()));
  }

  public function getConfiguration() {
    return $this->configuration;
  }

  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return TRUE;
  }

  public function getType() {
    return $this->pluginType;
  }

  public function configurationForm(array $form, array &$form_state, array $options = array()) {
    return array();
  }

  public function selectionElement($name, array $options = array()) {
    return array();
  }
}
