<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\ImageEffect\TextimageGifTransparency.
 */

namespace Drupal\textimage\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;

/**
 * Define the Textimage GIF transparency color.
 *
 * @ImageEffect(
 *   id = "textimage_gif_transparency",
 *   label = @Translation("Textimage GIF transparency"),
 *   description = @Translation("Define a color to set GIF transparency.")
 * )
 */
class TextimageGifTransparency extends TextimageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_replace_recursive(
      array(
        'gif_transparency_color' => NULL,
      ),
      parent::defaultConfiguration()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = array();

    // GIF transparency color.
    $form['gif_transparency_color'] = array(
      '#type' => 'textimage_color',
      '#title' => $this->t('Color'),
      '#description'  => $this->t('Select the color to be used for GIF transparency. <b>Note:</b> this will only be used by Textimage effects.'),
      '#allow_transparent' => FALSE,
      '#allow_opacity' => FALSE,
      '#default_value' => $this->configuration['gif_transparency_color'],
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;
    if ($data['gif_transparency_color']) {
      $data['gif_transparency_color_detail'] = array(
        '#theme' => 'textimage_color_detail',
        '#color' => $data['gif_transparency_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      );
    }
    return array(
      '#theme' => 'textimage_gif_transparency_summary',
      '#data' => $data,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Stores GIF transparency color for later effects.
    if ($this->configuration['gif_transparency_color']) {
      $this->textimageFactory->setState('gif_transparency_color', $this->configuration['gif_transparency_color']);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions) {
  }

}
