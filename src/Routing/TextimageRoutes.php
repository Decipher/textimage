<?php

declare(strict_types=1);

namespace Drupal\textimage\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a route for serving Textimages through the URL.
 */
class TextimageRoutes implements ContainerInjectionInterface {

  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(StreamWrapperManagerInterface::class),
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes(): array {
    $routes = [];

    // Route for generation of textimages from URL.
    $stream_wrapper = $this->streamWrapperManager->getViaScheme('public');
    if (method_exists($stream_wrapper, 'getDirectoryPath')) {
      // Route for direct URL Textimage generation.
      // If the textimage derivative does not exist, Drupal will create it
      // via TextimageDownloadController::urlDeliver.
      // If the textimage derivative already exists, the web server will
      // deliver it directly.
      $routes['textimage.public'] = new Route(
        '/' . $stream_wrapper->getDirectoryPath() . '/textimage/{image_style}',
        [
          '_controller' => 'Drupal\textimage\Controller\TextimageDownloadController::urlDeliver',
          '_disable_route_normalizer' => TRUE,
        ],
        ['_permission' => 'generate textimage url derivatives']
      );

      // Route for deferred generation in public scheme.
      // If the textimage derivative does not exist, Drupal will create it
      // via TextimageDownloadController::deferredDelivery.
      // If the textimage derivative already exists, the web server will
      // deliver it directly.
      $routes['textimage_store.public'] = new Route(
        '/' . $stream_wrapper->getDirectoryPath() . '/textimage_store',
        [
          '_controller' => 'Drupal\textimage\Controller\TextimageDownloadController::deferredDelivery',
          '_disable_route_normalizer' => TRUE,
        ],
        ['_access' => 'TRUE']
      );
    }

    if ($this->streamWrapperManager->getViaScheme('private')) {
      // Route for deferred generation in private scheme.
      // If the textimage derivative does not exist, Drupal will create it
      // via TextimageDownloadController::deferredDelivery.
      // If the textimage derivative already exists, Drupal will
      // deliver it via file_download.
      $routes['textimage_store.private'] = new Route(
        '/system/files/textimage_store',
        [
          '_controller' => 'Drupal\textimage\Controller\TextimageDownloadController::deferredDelivery',
          '_disable_route_normalizer' => TRUE,
        ],
        ['_access' => 'TRUE']
      );
    }

    return $routes;
  }

}
