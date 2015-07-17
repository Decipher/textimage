<?php

/**
 * @file
 * Contains \Drupal\textimage\Routing\TextimageRoutes.
 */

namespace Drupal\textimage\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a route for serving Textimages through the URL.
 */
class TextimageRoutes implements ContainerInjectionInterface {

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a new PathProcessorImageStyles object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager, ConfigFactoryInterface $config_factory) {
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->config = $config_factory->get('system.file');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {

    $routes = array();

    // Generate public textimages from URL. For local default filesystem only.
    // If clean URLs are disabled textimage derivatives will always be served
    // through the menu system.
    // If clean URLs are enabled and the textimage derivative already exists,
    // PHP will be bypassed.
    // @todo should only be done for public files, isn't it?
    $stream_wrapper = $this->streamWrapperManager->getViaScheme($this->config->get('default_scheme'));
    if ($stream_wrapper instanceof LocalStream) {
      $routes['textimage.public'] = new Route(
        '/' . $stream_wrapper->getDirectoryPath() . '/textimage/{image_style}/{text_string}',
        ['_controller' => 'Drupal\textimage\Controller\TextimageDownloadController::urlDeliver'],
        ['_permission' => 'generate textimage url derivatives']
      );
    }

    return $routes;
  }

}
