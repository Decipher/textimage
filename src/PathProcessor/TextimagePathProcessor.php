<?php

declare(strict_types=1);

namespace Drupal\textimage\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite Textimage URLs.
 *
 * Supports deferred Textimage generation from textimage formatter themes for
 * both public and private stream wrappers, and direct URL generation of
 * derivatives.
 */
class TextimagePathProcessor implements InboundPathProcessorInterface {

  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound(/* string */ $path, Request $request): string {
    $stream = $this->streamWrapperManager->getViaScheme('public');
    assert($stream instanceof LocalStream);
    $public_directory_path = $stream->getDirectoryPath();
    if (str_starts_with($path, '/' . $public_directory_path . '/textimage_store/')) {
      // Path is for deferred Textimage generation from public scheme.
      $path_prefix = '/' . $public_directory_path . '/textimage_store';
      // Strip out path prefix.
      $rest = preg_replace('|^' . preg_quote($path_prefix . '/', '|') . '|', '', $path);
      // Set the file as query parameter.
      $request->query->set('file', $rest);
      return $path_prefix;
    }
    if (str_starts_with($path, '/system/files/textimage_store/')) {
      // Path is for deferred Textimage generation from private scheme.
      $path_prefix = '/system/files/textimage_store';
      // Strip out path prefix.
      $rest = preg_replace('|^' . preg_quote($path_prefix . '/', '|') . '|', '', $path);
      // Set the file as query parameter.
      $request->query->set('file', $rest);
      return $path_prefix;
    }
    if (str_starts_with($path, '/' . $public_directory_path . '/textimage/')) {
      // Path is for direct URL Textimage generation.
      $path_prefix = '/' . $public_directory_path . '/textimage';
      // Strip out path prefix.
      $rest = preg_replace('|^' . preg_quote($path_prefix . '/', '|') . '|', '', $path);
      // Get the image style and text.
      if (substr_count($rest, '/') >= 1) {
        [$image_style, $text] = explode('/', $rest, 2);
        // Set the text as query parameter.
        $request->query->set('text', $text);
        return $path_prefix . '/' . $image_style;
      }
      return $path;
    }
    return $path;
  }

}
