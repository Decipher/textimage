<?php

declare(strict_types=1);

namespace Drupal\textimage\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\system\FileDownloadController;
use Drupal\textimage\TextimageException;
use Drupal\textimage\TextimageFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a controller to serve image styles.
 */
class TextimageDownloadController extends FileDownloadController implements ContainerInjectionInterface {

  public function __construct(
    protected readonly TextimageFactory $textimageFactory,
    protected readonly ImageFactory $imageFactory,
    ConfigFactoryInterface $configFactory,
    protected readonly LoggerInterface $logger,
    protected readonly FileSystemInterface $fileSystem,
    StreamWrapperManagerInterface $streamWrapperManager,
  ) {
    $this->configFactory = $configFactory;
    parent::__construct($streamWrapperManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('textimage.factory'),
      $container->get('image.factory'),
      $container->get('config.factory'),
      $container->get('logger.channel.textimage'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * Deliver directly a Textimage from the URL request.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object. The 'text' query parameter coming from the URL
   *   contains the text elements to be used to deliver the Textimage.
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
  public function urlDeliver(Request $request, ImageStyleInterface $image_style): BinaryFileResponse|Response {
    // Check if the URL generation is enabled.
    if (!$this->configFactory->get('textimage.settings')->get('url_generation.enabled')) {
      throw new AccessDeniedHttpException('Textimage URL generation is not enabled on this site');
    }

    // Check if the style exists, is relevant, and set to 'public' scheme in
    // TPS.
    if (!$this->textimageFactory->isTextimage($image_style)) {
      $this->logger->error("URL generation - The image style '%style_name' is not relevant for Textimage.", ['%style_name' => $image_style->getName()]);
      throw new NotFoundHttpException("The image style requested is not relevant for Textimage");
    }
    if ($image_style->getThirdPartySetting('textimage', 'uri_scheme', $this->configFactory->get('system.file')->get('default_scheme')) !== 'public') {
      $this->logger->error("URL generation - The image style '%style_name' is not set to produce image files for the 'public' file scheme -> disabled.", ['%style_name' => $image_style->getName()]);
      throw new AccessDeniedHttpException("The image style requested is not set to produce image files for the 'public' file scheme");
    }

    // {Text_0}[sep]{Text_1}[sep]...[sep]{Text_n} to the $text array.
    $text_string = $request->query->get('text');
    $text = explode($this->configFactory->get('textimage.settings')->get('url_generation.text_separator'), $text_string);

    // Manage the [extension].
    $last_text = array_pop($text);
    $extension = pathinfo($last_text, PATHINFO_EXTENSION);
    if ($extension) {
      $text[] = str_replace('.' . $extension, '', pathinfo($last_text, PATHINFO_BASENAME));
    }
    else {
      $this->logger->error("URL generation - No file extension specified.");
      throw new NotFoundHttpException('No file extension specified');
    }

    // Get the Textimage URI.
    $file_uri = 'public://textimage/' . $image_style->id() . '/' . $text_string;
    try {
      $image_uri = $this->textimageFactory->get()
        ->setStyle($image_style)
        ->setTargetUri($file_uri)
        ->process($text)
        ->buildImage()
        ->getUri();
      return $this->returnBinary($request, $image_uri);
    }
    catch (TextimageException $e) {
      $this->logger->error("URL generation - Failed to build an image at '%file_uri'.", ['%file_uri' => $file_uri]);
      throw new NotFoundHttpException('Image not found');
    }
  }

  /**
   * Deliver a Textimage from a deferred request.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the textimage ID is not found.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   */
  public function deferredDelivery(Request $request): BinaryFileResponse|Response {
    // Identify Textimage id.
    $file = $request->query->get('file');
    $tiid = str_replace('.' . pathinfo($file, PATHINFO_EXTENSION), '', pathinfo($file, PATHINFO_BASENAME));

    // Get the Textimage URI.
    try {
      $image_uri = $this->textimageFactory
        ->load($tiid)
        ->buildImage()
        ->getUri();
      return $this->returnBinary($request, $image_uri);
    }
    catch (TextimageException $e) {
      $this->logger->error("Failed to build an image at '%file_uri'.", ['%file_uri' => $file]);
      throw new NotFoundHttpException('Image not found');
    }
  }

  /**
   * Returns the image file at URI.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $uri
   *   The URI of the file to be returned.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   */
  protected function returnBinary(Request $request, $uri): BinaryFileResponse|Response {
    // Don't try to send file if it is missing.
    if (!file_exists($uri)) {
      $this->logger->notice("Textimage image at '%source_image_path' not found.", ['%source_image_path' => $uri]);
      return new Response($this->t('Error downloading a textimage.'), 404);
    }

    if (($scheme = $this->streamWrapperManager->getScheme($uri)) == 'private') {
      // If using the private scheme, defer control to FileDownloadController.
      $request->query->set('file', $this->streamWrapperManager->getTarget($uri));
      return parent::download($request, $scheme);
    }
    else {
      // Get the image and transfer to client.
      $image = $this->imageFactory->get($uri);
      $uri = $image->getSource();
      $headers = [
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      ];
      return new BinaryFileResponse($uri, 200, $headers);
    }
  }

}
