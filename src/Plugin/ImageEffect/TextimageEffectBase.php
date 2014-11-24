<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageEffect\TextimageEffectBase.
 */

namespace Drupal\textimage\Plugin\ImageEffect;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\ImageToolkit\ImageToolkitOperationManagerInterface;
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ImageFactory $image_factory, $textimage_factory, TextimageFontPluginInterface $font_plugin, TextimageBackgroundPluginInterface $background_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
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
      $container->get('logger.factory')->get('image'),
      $container->get('image.factory'),
      $container->get('textimage.factory'),
      $container->get('plugin.manager.textimage.font')->getPlugin(),
      $container->get('plugin.manager.textimage.background')->getPlugin()
    );
  }

}
