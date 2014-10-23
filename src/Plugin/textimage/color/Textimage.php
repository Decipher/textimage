<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\textimage\color\Textimage.
 */

namespace Drupal\textimage\Plugin\textimage\color;

use Drupal\Core\Form\FormStateInterface;
use Drupal\textimage\Plugin\TextimageColorPluginInterface;
use Drupal\textimage\Plugin\TextimagePluginBase;

/**
 * Basic color plugin for Textimage.
 *
 * Provides access to images stored in a directory, specified in configuration.
 *
 * @Plugin(
 *   id = "textimage",
 *   title = @Translation("Textimage basic color handler"),
 *   short_title = @Translation("Textimage"),
 *   help = @Translation("Use an HTML5 color element to select colors.")
 * )
 */
class Textimage extends TextimagePluginBase implements TextimageColorPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function selectionElement($name, array $options = array()) {
    $element[$name] = array(
      '#type' => 'color',
      '#title'   => isset($options['#title']) ? $options['#title'] : $this->t('Color'),
      '#description' => isset($options['#description']) ? $options['#description'] : NULL,
      '#default_value' => $options['#default_value'],
      '#maxlength' => 7,
      '#size' => 7,
    );
    return $element;
  }
}
