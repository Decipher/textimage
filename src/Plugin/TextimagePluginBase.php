<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\TextimagePluginBase.
 */

namespace Drupal\textimage\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin for Textimage.
 */
abstract class TextimagePluginBase extends PluginBase implements TextimagePluginBaseInterface {

  protected $pluginType;

  /**
   * Textimage configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('textimage.settings');
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginType = $configuration['plugin_type'];
    $config = $this->config->get($this->pluginType . '.plugin_settings.' . $plugin_id);
    $this->setConfiguration(array_merge($this->defaultConfiguration(), is_array($config) ? $config : array()));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
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
  public function calculateDependencies() {
    return parent::calculateDependencies();
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
