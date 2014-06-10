<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageEffect\TextimageEffectBase.
 */

namespace Drupal\textimage\Plugin\ImageEffect;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\textimage\Plugin\TextimageBackgroundPluginInterface;
use Drupal\textimage\Plugin\TextimageFontPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Textimage image effects.
 */
abstract class TextimageEffectBase extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * The Textimage factory.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $textimageFactory;  // @todo maybe not needed if there's a way to store data in the Image options

  /**
   * The Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Textimage configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The font plugin.
   *
   * @var \Drupal\textimage\Plugin\TextimageFontPluginInterface
   */
  protected $fontPlugin;

  /**
   * The background plugin.
   *
   * @var \Drupal\textimage\Plugin\TextimageBackgroundPluginInterface
   */
  protected $backgroundPlugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, ImageFactory $image_factory, $textimage_factory, TextimageFontPluginInterface $font_plugin, TextimageBackgroundPluginInterface $background_plugin) {
    $this->config = $config_factory->get('textimage.settings');
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->imageFactory = $image_factory;
    $this->textimageFactory = $textimage_factory;
    $this->fontPlugin = $font_plugin;
    $this->backgroundPlugin = $background_plugin;
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
      $container->get('image.factory'),
      $container->get('textimage.factory'),
      $container->get('plugin.manager.textimage.font')->getPlugin(),
      $container->get('plugin.manager.textimage.background')->getPlugin()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, array &$form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration = $form_state['values'];
  }

}
