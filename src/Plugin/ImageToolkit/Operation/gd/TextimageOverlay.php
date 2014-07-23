<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageToolkit\Operation\gd\TextimageOverlay.
 */

namespace Drupal\textimage\Plugin\ImageToolkit\Operation\gd;

use Drupal\textimage\Component\ColorUtility;

/**
 * Defines Textimage GD2 overlay operation.
 *
 * @todo temp while imagecache_action develops
 *
 * NOTE that the PHP libraries are not great at merging images SO we include a
 * library that does it pixel-by-pixel which is INCREDIBLY inefficient. If this
 * can be improved, in a way that supports all transparency, please let us know!
 *
 * A watermark is layer onto image, return the image. An underlay is image onto
 * layer, return the layer. Almost identical, but seeing as we work with
 * resource handles, the handle needs to be swapped before returning.
 *
 * @ImageToolkitOperation(
 *   id = "textimage_gd_textimage_overlay",
 *   toolkit = "gd",
 *   operation = "textimage_overlay",
 *   label = @Translation("Textimage overlay image"),
 *   description = @Translation("Overlays an image over or under the current.")
 * )
 */
class TextimageOverlay extends GDTextimageOperationBase {

  /**
   * {@inheritdoc}
   */
  protected function arguments() {
    return array(
      'layer' => array(
        'description' => 'Image object to be placed over or under the current image',
      ),
      'x' => array(
        'description' => 'x-position of the overlay',
      ),
      'y' => array(
        'description' => 'y-position of the overlay',
      ),
      'alpha' => array(
        'description' => 'Transparency of the overlay from 0-100. 0 is totally transparent. 100 (default) is totally opaque.',
        'required' => FALSE,
        'default' => 100,
      ),
      'reverse' => array(
        'description' => 'Flag to indicate the \'overlay\' actually goes under the image',
        'required' => FALSE,
        'default' => FALSE,
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function validateArguments(array $arguments) {
    if ($arguments['reverse']) {
      $arguments['x'] = imagecache_actions_keyword_filter($arguments['x'], $arguments['layer']->getWidth(), $this->getToolkit()->getWidth());
      $arguments['y'] = imagecache_actions_keyword_filter($arguments['y'], $arguments['layer']->getHeight(), $this->getToolkit()->getHeight());
    }
    else {
      $arguments['x'] = imagecache_actions_keyword_filter($arguments['x'], $this->getToolkit()->getWidth(), $arguments['layer']->getWidth());
      $arguments['y'] = imagecache_actions_keyword_filter($arguments['y'], $this->getToolkit()->getHeight(), $arguments['layer']->getHeight());
    }

    return $arguments;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // If the given alpha is 100%, we can use imagecopy - which actually works,
    // is more efficient, and seems to retain the overlays partial transparency.
    // Still does not work great for indexed gifs though?
    if ($arguments['reverse']) {
      $upper = $this->getToolkit()->getImage();
      $lower = $arguments['layer'];
    }
    else {
      $upper = $arguments['layer'];
      $lower = $this->getToolkit()->getImage();
    }
    if ($arguments['alpha'] == 100 && ($upper->getMimeType() != 'image/gif')) {
      imagealphablending($lower->getToolkit()->getResource(), TRUE);
      imagesavealpha($lower->getToolkit()->getResource(), TRUE);
      imagealphablending($upper->getToolkit()->getResource(), TRUE);
      imagesavealpha($upper->getToolkit()->getResource(), TRUE);
      imagecopy($lower->getToolkit()->getResource(), $upper->getToolkit()->getResource(), $arguments['x'], $arguments['y'], 0, 0, $upper->getWidth(), $upper->getHeight());
      imagedestroy($upper->getToolkit()->getResource());
      $this->getToolkit()->setResource($lower->getToolkit()->getResource());
    }
  /*/  else {
      // imagecopy() cannot be used and we have to use the slow library.
      module_load_include('inc', 'imagecache_actions', 'watermark');
      $watermark = new watermark();
      $result_img = $watermark->create_watermark($lower->getToolkit()->getResource(), $upper->getToolkit()->getResource(), $arguments['x'], $arguments['y'], $arguments['alpha']);
      // Watermark creates a new image resource, so clean up both old images.
      imagedestroy($lower->getToolkit()->getResource());
      imagedestroy($upper->getToolkit()->getResource());
      $image->getToolkit()->setResource($result_img);
    }*/

    return TRUE;
  }

}
