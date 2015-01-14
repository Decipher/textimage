<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\textimage\background\Textimage.
 */

namespace Drupal\textimage\Plugin\textimage\background;

use Drupal\Core\Form\FormStateInterface;
use Drupal\textimage\Plugin\TextimageBackgroundPluginInterface;
use Drupal\textimage\Plugin\TextimagePluginBase;

/**
 * Basic background image plugin for Textimage.
 *
 * Provides access to images stored in a directory, specified in configuration.
 *
 * @Plugin(
 *   id = "textimage",
 *   title = @Translation("Textimage basic image handler"),
 *   short_title = @Translation("Textimage"),
 *   help = @Translation("Access images stored in the directory specified in configuration.")
 * )
 */
class Textimage extends TextimagePluginBase implements TextimageBackgroundPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array('path' => 'private://textimage_store/backgrounds');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $ajax_settings = []) {
    $element['path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#default_value' => $this->configuration['path'],
      '#element_validate' => array(array($this, 'validatePath')),
      '#maxlength' => 255,
      '#description' =>
        $this->t('Location of the directory where the background images are stored.') . ' ' .
        $this->t('Relative paths will be resolved relative to the Drupal installation directory.'),
    );
    return $element;
  }

  /**
   * Validation handler for the 'path' element.
   */
  public function validatePath($element, FormStateInterface $form_state, $form) {
    if (!is_dir($element['#value'])) {
      $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('Invalid directory specified.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = array()) {
    // Get list of images.
    $image_files = $this->getList();
    if (empty($image_files)) {
      $this->logger->warning($this->t('No background images available. Make sure at least one image is available in the directory specified in the <a href="!url">configuration page</a>.', ['!url' => $this->urlGenerator->generateFromRoute('textimage.settings')]));
    }
    // Element.
    return array(
      '#type'  => 'select',
      '#title'   => isset($options['#title']) ? $options['#title'] : $this->t('Background image'),
      '#description' => isset($options['#description']) ? $options['#description'] : $this->t('Select image.'),
      '#options' => array_combine($image_files, $image_files),
      '#default_value' => isset($options['background_image']['uri']) ? pathinfo($options['background_image']['uri'], PATHINFO_BASENAME) : '',
      '#element_validate' => array(array($this, 'validateSelectorUri')),
      '#states' => array(
        'visible' => array(
          ':input[name="data[background_image][mode]"]' => array('value' => 'select'),
        ),
      ),
    );
  }

  /**
   * Validation handler for the selection element.
   */
  public function validateSelectorUri($element, FormStateInterface $form_state, $form) {
    if ($form_state->getValue(array('data', 'background_image', 'mode')) == 'select') {
      $file_path = $this->configuration['path'] . '/' . $element['#value'];
      if (!file_exists($file_path)) {
        $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('The file selected does not exist.'));
      }
      else {
        $form_state->setValue(array('data', 'background_image', 'uri'), $file_path);
      }
    }
  }

  /**
   * Returns an array of files with image extensions in the specified directory.
   *
   * @param string $images_dir
   *   URL of the images directory.
   *
   * @return array
   *   Array of image files.
   */
  protected function getList() {
    $filelist = array();
    if (is_dir($this->configuration['path']) && $handle = opendir($this->configuration['path'])) {
      while ($file = readdir($handle)) {
        if (preg_match("/\.gif|\.png|\.jpg|\.jpeg$/i", $file) == 1) {
          $filelist[] = $file;
        }
      }
      closedir($handle);
    }
    return $filelist;
  }

}
