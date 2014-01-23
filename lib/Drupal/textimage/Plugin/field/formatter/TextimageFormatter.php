<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\field\formatter\TextimageFormatter.
 */

namespace Drupal\textimage\Plugin\field\formatter;

use Drupal\Core\Entity\Field\FieldInterface;

/**
 * Plugin implementation of the 'textimage' formatter.
 *
 * @FieldFormatter(
 *   id = "textimage",
 *   label = @Translation("Textimage"),
 *   field_types = {
 *     "text",
 *     "text_with_summary",
 *     "text_long",
 *     "image"
 *   },
 *   settings = {
 *     "image_style" = ""
 *   }
 * )
 */
class TextimageFormatter extends TextimageFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, array &$form_state) {
    // @todo to-be $image_styles = TextimageStyles::getOptions(FALSE);
    $image_styles = image_style_options(FALSE); // @todo remove
    $element['image_style'] = array(
      '#title' => t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
// @todo remove      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
      '#required' => TRUE,
      '#description' => t('Only Textimage relevant image styles can be selected.'),
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    // @todo to-be $image_styles = TextimageStyles::getOptions(FALSE);
    $image_styles = image_style_options(FALSE); // @todo remove
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = t('Image style: @style', array('@style' => $image_styles[$image_style_setting]));
    }
    else {
      $summary[] = t('Image style: undefined');
    }

    return $summary;
  }

/**
 * Implements hook_field_formatter_view().
 */
/*function textimage_field_formatter_view($entity_type, $entity, $field, $instance, $langcode, $items, $display) {

  // If formatting a node, store entity for passing to theme.
  // The node entity will be used for the detokening of text.
  $node = ($entity_type == 'node') ? $entity : NULL;

  $element = array();

  if ($field['module'] == 'text') {
    // Get sanitized text strings from a text field.
    $text = TextimageImager::getTextFieldText($items, $field, $instance, $node);
    $element[] = array(
      '#theme' => 'textimage_style_image',
      '#style_name' => $display['settings']['image_style'],
      '#text' => $text,
      '#node' => $node,
    );
  }
  elseif ($field['module'] == 'image') {
    // Get source image from an image field.
    foreach ($items as $delta => $item) {
      $source_image_file = file_load($item['fid']);
      $element[$delta] = array(
        '#theme' => 'textimage_style_image',
        '#style_name' => $display['settings']['image_style'],
        '#text' => NULL,
        '#node' => $node,
        '#source_image_file' => $source_image_file,
      );
    }
  }

  return $element;
}*/

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldInterface $items) {
    $elements = array();

    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      $uri = $items->getEntity()->uri();
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');
    foreach ($items as $delta => $item) {
//dpm($item);
      if ($item->entity) {
        if (isset($link_file)) {
          $image_uri = $item->entity->getFileUri();
          $uri = array(
            'path' => file_create_url($image_uri),
            'options' => array(),
          );
        }
        $elements[$delta] = array(
          '#theme' => 'image_formatter',
          '#item' => $item->getValue(TRUE),
          '#image_style' => $image_style_setting,
          '#path' => isset($uri) ? $uri : '',
        );
      }
    }

    return $elements;
  }

}
