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

}
