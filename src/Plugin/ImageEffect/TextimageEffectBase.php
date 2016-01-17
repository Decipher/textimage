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
use Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface;
use Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface;
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
   * The font selector plugin.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsFontSelectorPluginInterface
   */
  protected $fontSelector;

  /**
   * The image selector plugin.
   *
   * @var \Drupal\image_effects\Plugin\ImageEffectsPluginBaseInterface
   */
  protected $imageSelector;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, LoggerInterface $logger, ImageFactory $image_factory, $textimage_factory, ImageEffectsFontSelectorPluginInterface $font_selector_plugin, ImageEffectsPluginBaseInterface $image_selector_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->moduleHandler = $module_handler;
    $this->imageFactory = $image_factory;
    $this->textimageFactory = $textimage_factory;
    $this->fontSelector = $font_selector_plugin;
    $this->imageSelector = $image_selector_plugin;
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
      $container->get('plugin.manager.image_effects.font_selector')->getPlugin(),
      $container->get('plugin.manager.image_effects.image_selector')->getPlugin()
    );
  }

}
