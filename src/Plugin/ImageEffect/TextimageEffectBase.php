<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageEffect\TextimageEffectBase.
 */

namespace Drupal\textimage\Plugin\ImageEffect;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\textimage\Plugin\TextimageBackgroundPluginInterface;
use Drupal\textimage\Plugin\TextimageFontPluginInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Textimage image effects.
 */
abstract class TextimageEffectBase extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Textimage factory.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $textimageFactory;

  /**
   * The Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, LoggerInterface $logger, ImageFactory $image_factory, $textimage_factory, TextimageFontPluginInterface $font_plugin, TextimageBackgroundPluginInterface $background_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler'),
      $container->get('textimage.logger'),
      $container->get('image.factory'),
      $container->get('textimage.factory'),
      $container->get('plugin.manager.textimage.font')->getPlugin(),
      $container->get('plugin.manager.textimage.background')->getPlugin()
    );
  }

}
