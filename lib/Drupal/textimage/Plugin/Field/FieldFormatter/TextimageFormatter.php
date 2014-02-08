<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\Field\FieldFormatter\TextimageFormatter.
 */


namespace Drupal\textimage\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

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
 *     "image",
 *   },
 *   settings = {
 *     "image_style" = "",
 *     "image_link" = "",
 *   }
 * )
 */
class TextimageFormatter extends FormatterBase {

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

    $link_types = array(
      'content' => t('Content'),
      'file' => t('File'),
    );
    $element['image_link'] = array(
      '#title' => t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => t('Nothing'),
      '#options' => $link_types,
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

    $link_types = array(
      'content' => t('Linked to content'),
      'file' => t('Linked to file'),
    );
    // Display this setting only if image is linked.
    if (isset($link_types[$this->getSetting('image_link')])) {
      $summary[] = $link_types[$this->getSetting('image_link')];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items) {

    $instance = $items->getFieldDefinition();
    $field = $instance->getField();
    
    // If formatting a node, store entity for passing to theme.
    // The node entity will be used for the detokening of text.
    $node = ($instance->entity_type == 'node') ? $items->getEntity() : NULL;

    // Check if the formatter involves a link.
    $href = NULL;
    if ($image_link_setting = $this->getSetting('image_link')) {
      switch ($image_link_setting) {
        case 'content':
          $uri = $items->getEntity()->urlInfo();
          // @todo Remove when theme_textimage_formatter() has support for route name.
          $uri['path'] = $items->getEntity()->getSystemPath();
          $href = $uri['path'];
          break;

        case 'file':
          $href = '#textimage_derivative_url#';
          break;

      }
    }

    $elements = array();

    if ($field->module == 'text') {
      // Get sanitized text strings from a text field.
      $text = \Drupal::service('textimage.factory')->getTextFieldText($items);
      $elements[] = array(
        '#theme' => 'textimage_formatter',
        '#style_name' => $this->getSetting('image_style'),
        '#text' => $text,
        '#node' => $node,
        '#alt' => $variables['alt'] ? $variables['alt'] : implode(' ', $text),
        '#href' => $href,
      );
    }
    elseif ($field->module == 'image') {
      // Get source image from an image field.
      foreach ($items as $delta => $item) {
        $elements[$delta] = array(
          '#theme' => 'textimage_formatter',
          '#style_name' => $this->getSetting('image_style'),
          '#text' => NULL,
          '#node' => $node,
          '#source_image_file' => $item->entity,
          '#href' => $href,
        );
      }
    }

    return $elements;
  }

}
