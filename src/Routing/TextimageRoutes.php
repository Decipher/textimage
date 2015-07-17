<?php

/**
 * @file
 * Contains \Drupal\textimage\Routing\TextimageRoutes.
 */

namespace Drupal\textimage\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
   * Constructs a new TextimageRoutes object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stream_wrapper_manager')
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

    // Route for generation of textimages from URL.
    // If the textimage derivative does not exist, Drupal will create the
    // derivative via TextimageDownloadController::urlDeliver.
    // If the textimage derivative already exists, the web server will deliver
    // it directly.
    $stream_wrapper = $this->streamWrapperManager->getViaScheme('public');
    if (method_exists($stream_wrapper, 'getDirectoryPath')) {
      $routes['textimage.public'] = new Route(
        '/' . $stream_wrapper->getDirectoryPath() . '/textimage/{image_style}/{text_string}',
        ['_controller' => 'Drupal\textimage\Controller\TextimageDownloadController::urlDeliver'],
        ['_permission' => 'generate textimage url derivatives']
      );
    }

    return $routes;
  }

}
