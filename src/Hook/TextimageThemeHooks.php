<?php

declare(strict_types=1);

namespace Drupal\textimage\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;

/**
 * Theme hook implementations for textimage.
 */
class TextimageThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      // Format a textimage.
      'textimage_formatter' => [
        'variables' => [
          'item' => NULL,
          'uri' => NULL,
          'width' => NULL,
          'height' => NULL,
          'alt' => '',
          'title' => '',
          'attributes' => [],
          'image_container_attributes' => [],
          'anchor_url' => NULL,
        ],
        'initial preprocess' => self::class . ':preprocessTextimageFormatter',
      ],
    ];
  }

  /**
   * Prepares variables to get a Textimage from text.
   *
   * Default template: textimage-formatter.html.twig.
   */
  public function preprocessTextimageFormatter(array &$variables): void {
    // Render only if the image URI is passed in.
    if ($image_uri = $variables['uri']) {
      // Get alt and title from field item if missing from variables.
      if (!$variables['title'] && $variables['item']) {
        $variables['title'] = $variables['item']->getValue()['title'];
      }
      if (!$variables['alt'] && $variables['item']) {
        $variables['alt'] = $variables['item']->getValue()['alt'];
      }

      // Build anchor link data if needed.
      if ($variables['anchor_url']) {
        if (is_string($variables['anchor_url'])) {
          $href = $variables['anchor_url'];
        }
        else {
          $href = $variables['anchor_url']->toString();
        }
        $variables['anchor_attributes'] = new Attribute([
          'title' => $variables['title'],
          'href' => $href,
        ]);
      }

      // Build image container attributes if needed.
      if (!empty($variables['image_container_attributes'])) {
        $variables['image_container_attributes'] = new Attribute($variables['image_container_attributes']);
      }

      // Get the <img>.
      $variables['image'] = [
        '#theme' => 'image__textimage',
        '#uri' => $image_uri,
        '#width' => $variables['width'],
        '#height' => $variables['height'],
        '#attributes' => $variables['attributes'],
      ];
      if (!empty($variables['title'])) {
        $variables['image']['#title'] = $variables['title'];
      }
      if (!empty($variables['alt'])) {
        $variables['image']['#alt'] = $variables['alt'];
      }
    }
  }

}
