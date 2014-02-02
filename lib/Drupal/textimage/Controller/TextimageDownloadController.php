<?php

/**
 * @file
 * Contains \Drupal\textimage\Controller\TextimageDownloadController.
 */

namespace Drupal\textimage\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\image\ImageStyleInterface;
use Drupal\system\FileDownloadController;
use Drupal\textimage\TextimageFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
   * @param string $text_string
   *   The text string, coming from the URL, to be used to deliver the
   *   Textimage.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when public Textimages can not be generated for the style.
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the image style is missing.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   */
  public function urlDeliver($text_string, ImageStyleInterface $image_style) {
    // Check if the style exists.
    if (empty($image_style)) {   // @todo should be a textimage_style, or???
      throw new NotFoundHttpException('Could not find the image style requested.');
    }
    /* @todo
    if ($style['textimage']['uri_scheme'] != 'public') {
      throw new AccessDeniedHttpException('URL delivery of Textimage images not allowed for this image style.');
      drupal_access_denied();
    }*/
    
    // {Text_0}[sep]{Text_1}[sep]...[sep]{Text_n} to the $text array.
    // @todo make separator configurable
    $text = explode('---', $text_string);

    // Manage the [extension].
    // @todo use Unicode
    $last_text = array_pop($text);
    $offset = strrpos($last_text, '.');
    if ($offset && (strlen($last_text) - $offset) <= 5) {
      $extension = substr($last_text, $offset + 1);
      $text[] = substr($last_text, 0, $offset);
    }
    else {
      $extension = 'png';
      $text[] = $last_text;
    }

    // @todo review
    // @todo temp hack to generate a fake entity if not existing
    $textimage_style = entity_create('textimage_style', array('id' => $image_style->id()));

    // Get the Textimage URI.
    $image_uri = $this->textimageFactory->processImageRequest(
      $textimage_style,
      NULL,
      $text,
      $extension
    );
    
    // Don't try to send file if it is missing.
    if (!file_exists($image_uri)) {
      watchdog('textimage', 'Teximage image at %source_image_path not found.',  array('%source_image_path' => $image_uri));
      return new Response($this->t('Error downloading a textimage.'), 404);
    }

    $headers = array();
    
    // If using the private scheme, let other modules provide headers and
    // control access to the file.
    // @todo should check the old lab02
    $scheme = file_uri_scheme($image_uri);
    if ($scheme == 'private') {
      if (file_exists($derivative_uri)) { // @todo not this
        return parent::download($request, $scheme);
      }
      else {
        $headers = $this->moduleHandler()->invokeAll('file_download', array($image_uri));
        if (in_array(-1, $headers) || empty($headers)) {
          throw new AccessDeniedHttpException();
        }
      }
    }

    // Get the image and transfer to client.
    $image = $this->imageFactory->get($image_uri);
    $uri = $image->getSource();
    $headers += array(
      'Content-Type' => $image->getMimeType(),
      'Content-Length' => $image->getFileSize(),
    );
    return new BinaryFileResponse($uri, 200, $headers);
  }

}
