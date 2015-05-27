<?php

/**
 * @file
 * Contains \Drupal\textimage\Controller\TextimageDownloadController.
 */

namespace Drupal\textimage\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\image\ImageStyleInterface;
use Drupal\system\FileDownloadController;
use Drupal\textimage\TextimageFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a controller to serve image styles.
 */
class TextimageDownloadController extends FileDownloadController implements ContainerInjectionInterface {

  /**
   * The Textimage factory.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $textimageFactory;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs a TextimageDownloadController object.
   *
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   */
  public function __construct(TextimageFactory $textimage_factory, ImageFactory $image_factory) {
    $this->textimageFactory = $textimage_factory;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('textimage.factory'),
      $container->get('image.factory')
    );
  }

  /**
   * Deliver directly a Textimage from the URL request.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $text_string
   *   The text string, coming from the URL, to be used to deliver the
   *   Textimage.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when Textimage URL generation is not enabled.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the image style is missing.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   */
  public function urlDeliver(Request $request, $text_string, ImageStyleInterface $image_style) {
    // Check if the URL generation is enabled.
    if (!$this->textimageFactory->getConfig()->get('url_generation.enabled')) {
      throw new AccessDeniedHttpException('Textimage URL generation is not enabled on this site');
    }

    // Check if the style exists.
    if (empty($image_style)) {
      throw new NotFoundHttpException('Could not find the image style requested');
    }
    if (!$this->textimageFactory->isTextimage($image_style)) {
      throw new NotFoundHttpException('The image style requested is not relevant for Textimage');
    }

    // {Text_0}[sep]{Text_1}[sep]...[sep]{Text_n} to the $text array.
    $text = explode($this->textimageFactory->getConfig()->get('url_generation.text_separator'), $text_string);

    // Manage the [extension].
    $last_text = array_pop($text);
    $offset = strrpos($last_text, '.');
    if ($offset && (Unicode::strlen($last_text) - $offset) <= 5) {
      $extension = Unicode::substr($last_text, $offset + 1);
      $text[] = Unicode::substr($last_text, 0, $offset);
    }
    else {
      $extension = 'png';
      $text[] = $last_text;
    }

    // Get the Textimage URI.
    $image_uri = $this->textimageFactory->getTextimage()
      ->style($image_style)
      ->extension($extension) // @todo verify this, should override the style setting if set
      ->process($text)
      ->getUri();

    // Don't try to send file if it is missing.
    if (!file_exists($image_uri)) {
      \Drupal::logger('textimage')->notice('Textimage image at %source_image_path not found.',  array('%source_image_path' => $image_uri));
      return new Response($this->t('Error downloading a textimage.'), 404);
    }

    if (($scheme = file_uri_scheme($image_uri)) == 'private') {
      // If using the private scheme, defer control to FileDownloadController.
      $request->query->set('file', file_uri_target($image_uri));
      return parent::download($request, $scheme);
    }
    else {
      // Get the image and transfer to client.
      $image = $this->imageFactory->get($image_uri);
      $uri = $image->getSource();
      $headers = array(
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      );
      return new BinaryFileResponse($uri, 200, $headers);
    }
  }

}
