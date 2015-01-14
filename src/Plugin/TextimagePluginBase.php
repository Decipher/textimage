<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\TextimagePluginBase.
 */

namespace Drupal\textimage\Plugin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base plugin for Textimage.
 */
abstract class TextimagePluginBase extends PluginBase implements TextimagePluginBaseInterface {

  /**
   * The Textimage plugin type (font/background/color).
   *
   * @var string
   */
  protected $pluginType;

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Textimage configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The Textimage logger.
   *
   * @var \Psr\Log\LoggerInterface.
   */
  protected $logger;

  /**
   * Constructs a TextimagePluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Textimage logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator, LoggerInterface $logger) {
    $this->config = $config_factory->get('textimage.settings');
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pluginType = $configuration['plugin_type'];
    $config = $this->config->get($this->pluginType . '.plugin_settings.' . $plugin_id);
    $this->setConfiguration(array_merge($this->defaultConfiguration(), is_array($config) ? $config : array()));
    $this->urlGenerator = $url_generator;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('url_generator'),
      $container->get('textimage.logger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
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

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->pluginType;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $ajax_settings = []) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) { }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) { }

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = array()) {
    return array();
  }
}
