<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\textimage\background\Media.
 */

namespace Drupal\textimage\Plugin\textimage\background;

use Drupal\Core\Form\FormStateInterface;
use Drupal\textimage\Plugin\TextimageBackgroundPluginInterface;
use Drupal\textimage\Plugin\TextimagePluginBase;

/**
 * Background image handler for Media module.
 *
 * Currently only provides access to fonts uploaded locally via the
 * local font uploading features.
 *
 * @Plugin(
 *   id = "media",
 *   title = @Translation("Image handler for Media module."),
 *   short_title = @Translation("Media"),
 *   help = @Translation("Use the Media browser to select images.")
 * )
 */
class Media extends TextimagePluginBase implements TextimageBackgroundPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function isAvailable() {
    return FALSE; // @todo check module!!
    $backgrounds_handling_module_options = array();
    $backgrounds_handling_module_options['textimage'] = $this->t('Textimage');
    if (_textimage_module_exists('media', TEXTIMAGE_MEDIA_MIN_VERSION)) {
      $backgrounds_handling_module_options['media'] = $this->t('Media');
    }
    $backgrounds_handling_module_option_selected = $form_state->hasValue('backgrounds_handling_module') ? $form_state->getValue('backgrounds_handling_module') : $this->config->get('backgrounds_handling_module');
  }

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = array()) {
      // Media module available - use media form element.
      if (!isset($this->configuration['background_image']['fid'])) {
        $this->configuration['background_image']['fid'] = 0;
      }
      $scheme_options = array();
      foreach (file_get_stream_wrappers(STREAM_WRAPPERS_WRITE_VISIBLE) as $scheme => $stream_wrapper) {
        $scheme_options[$scheme] = $scheme;
      }
      $form['background_image']['fid'] = array(
        '#title' => NULL,
        '#type' => 'media',
        '#media_options' => array(
          'global' => array(
            'types' => array('image'),
            'file_extensions' => 'png gif jpg jpeg',
            'schemes' => $scheme_options,
          ),
        ),
        '#description' => NULL,
        '#default_value' => array('fid' => $this->configuration['background_image']['fid']),
        '#element_validate' => array(array($this, 'validateSelectorUri')),
        '#states' => array(
          'visible' => array(
            ':input[name="data[background_image][mode]"]' => array('value' => 'select'),
          ),
        ),
      );
  }

  public function validateSelectorUri($element, &$form_state, $form) {
    $backgroundPlugin = \Drupal::service('plugin.manager.textimage.background')->getPlugin();
    $v = $form_state->getValue('data');
    if ($v['background_image']['mode'] == 'select' && !$v['background_image']['fid']['fid']) {
      $form_state->setErrorByName('background_image', t('Select an image, or choose another option for the background image.'));
      return;
    }
    if (isset($v['background_image']['fid'])) {
      $form_state->setValue(array('data', 'background_image', 'fid'), $v['background_image']['fid']['fid']);
      $file = file_load($v['background_image']['fid']);
      $form_state->setValue(array('data', 'background_image', 'uri'), $file->uri);
    }
    if (!isset($v['background_image']['fid']) and isset($v['background_image']['uri'])) {
      $pluginConfiguration = $backgroundPlugin->getConfiguration();
      $path = $pluginConfiguration['path'];
      $form_state->setValue(array('data', 'background_image', 'uri'), $path . '/' . $v['background_image']['uri']);
    }
  }


}
