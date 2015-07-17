<?php

/**
 * @file
 * Contains \Drupal\textimage\Routing\TextimageRoutes.
 */

namespace Drupal\textimage\Routing;

use Drupal\Core\StreamWrapper\LocalStream;
use Symfony\Component\Routing\Route;

/**
 * Defines a route for serving Textimages through the URL.
 */
class TextimageRoutes {

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
    $config = \Drupal::service('config.factory')->get('system.file'); // @todo proper injection
    $stream_wrapper = \Drupal::service('stream_wrapper_manager')->getViaScheme($config->get('default_scheme')); // @todo proper injection
    if ($stream_wrapper instanceof LocalStream) {
      $routes['textimage.public'] = new Route(
        '/' . $stream_wrapper->getDirectoryPath() . '/textimage/{image_style}/{text_string}',
        array(
          '_controller' => 'Drupal\textimage\Controller\TextimageDownloadController::urlDeliver',
        ),
        array(
          '_permission' => 'generate textimage url derivatives',
        )
      );
    }

    return $routes;
  }

}
