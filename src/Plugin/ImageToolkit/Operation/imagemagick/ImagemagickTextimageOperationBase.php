<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickTextimageOperationBase.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;
use Drupal\textimage\Plugin\ImageToolkit\Operation\TextimageOperationTrait;

/**
 * Base class for Textimage Imagemagick image toolkit operations.
 */
abstract class ImagemagickTextimageOperationBase extends ImagemagickImageToolkitOperationBase {

  use TextimageOperationTrait;

  /**
   * The format mapper service.
   *
   * @var \Drupal\imagemagick\ImagemagickFormatMapperInterface
   */
  protected $formatMapper;

  /**
   * Returns the format mapper service.
   *
   * @return \Drupal\imagemagick\ImagemagickFormatMapperInterface
   *   The format mapper service.
   */
  protected function getFormatMapper() {
    if (!$this->formatMapper) {
      $this->formatMapper = \Drupal::service('imagemagick.format_mapper');
    }
    return $this->formatMapper;
  }

}
