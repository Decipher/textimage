<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\Field\FieldFormatter\TextimageFormatter.
 */

namespace Drupal\textimage\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\textimage\TextimageFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'textimage' formatter.
 *
 * @FieldFormatter(
 *   id = "textimage",
 *   label = @Translation("Textimage"),
 *   field_types = {
 *     "string",
 *     "string_long",
 *     "text",
 *     "text_with_summary",
 *     "text_long",
 *     "image"
 *   }
 * )
 */
class TextimageFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The Textimage factory service.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $textimageFactory;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * Constructs an TextimageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style entity storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, LinkGeneratorInterface $link_generator, UrlGeneratorInterface $url_generator, TextimageFactory $textimage_factory, EntityStorageInterface $image_style_storage) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->currentUser = $current_user;
    $this->linkGenerator = $link_generator;
    $this->urlGenerator = $url_generator;
    $this->textimageFactory = $textimage_factory;
    $this->imageStyleStorage = $image_style_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('link_generator'),
      $container->get('url_generator'),
      $container->get('textimage.factory'),
      $container->get('entity.manager')->getStorage('image_style')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'image_style' => '',
      'image_link' => '',
      'image_alt' => '',
      'image_title' => '',
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    // Image style setting.
    $image_styles = $this->textimageFactory->getTextimageStyleOptions();
    if (empty($image_styles)) {
      $image_styles[''] = $this->t('No Textimage style available');
    }
    $element['image_style'] = array(
      '#title' => $this->t('Image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style'),
      '#options' => $image_styles,
      '#required' => TRUE,
      '#description' => array(
        '#markup' => $this->t('Only Textimage relevant image styles can be selected.'),
        'link' => array(
          '#markup' =>  ' ' . $this->linkGenerator->generate($this->t('Configure Image Styles'), new Url('entity.image_style.collection')),
          '#access' => $this->currentUser->hasPermission('administer image styles'),
        ),
      ),
    );

    // Link setting.
    $link_types = array(
      'content' => $this->t('Content'),
      'file' => $this->t('File'),
    );
    $element['image_link'] = array(
      '#title' => $this->t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => $this->t('Nothing'),
      '#options' => $link_types,
    );

    // Image alt and title attribute settings.
    $element['image_alt'] = array(
      '#title' => $this->t('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('image_alt'),
      '#description' => $this->t('This text will be used by screen readers, search engines, or when the image cannot be loaded.') . ' ' . $this->t('Tokens can be used.'),
      '#maxlength' => 512,
    );
    $element['image_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->getSetting('image_title'),
      '#description' => $this->t('The title is used as a tool tip when the user hovers the mouse over the image.') . ' ' . $this->t('Tokens can be used.'),
      '#maxlength' => 1024,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $image_styles = $this->textimageFactory->getTextimageStyleOptions();
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = $this->t('Image style: @style', array('@style' => $image_styles[$image_style_setting]));
    }
    else {
      $summary[] = $this->t('Image style: undefined');
    }

    // Display link setting only if image is linked.
    $link_types = array(
      'content' => $this->t('Linked to content'),
      'file' => $this->t('Linked to file'),
    );
    if (isset($link_types[$this->getSetting('image_link')])) {
      $summary[] = $link_types[$this->getSetting('image_link')];
    }

    // Display this setting only if alt text is specified.
    if ($this->getSetting('image_alt')) {
      $summary[] = $this->t('Alternative text: @image_alt', array('@image_alt' => $this->getSetting('image_alt')));
    }

    // Display this setting only if title is specified.
    if ($this->getSetting('image_title')) {
      $summary[] = $this->t('Title: @image_title', array('@image_title' => $this->getSetting('image_title')));
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items) {

    $instance = $items->getFieldDefinition();
    $field = $instance->getFieldStorageDefinition();

    // If formatting a node, store entity for passing to theme.
    // The node entity will be used for the detokening of text.
    $node = ($instance->getTargetEntityTypeId() == 'node') ? $items->getEntity() : NULL;

    // Check if the formatter involves a link.
    $url = NULL;
    if ($image_link_setting = $this->getSetting('image_link')) {
      switch ($image_link_setting) {
        case 'content':
          $url = array(
            'path' => $items->getEntity()->getSystemPath(),
            'options' => $items->getEntity()->urlInfo()->getOptions(),
          );
          break;

        case 'file':
          $url = array(
            'path' => '#textimage_derivative_url#',
            'options' => array(),
          );
          break;

      }
    }

    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $cache_tags = array();
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $cache_tags = $image_style->getCacheTags();
    }

    $elements = array();

    switch($field->getTypeProvider()) {
      case 'text':
      case 'core';
        // Get sanitized text strings from a text field.
        $text = $this->textimageFactory->getTextFieldText($items);
        $elements[] = array(
          '#theme' => 'textimage_formatter',
          '#style_name' => $this->getSetting('image_style'),
          '#text' => $text,
          '#node' => $node,
          '#force_hashed_filename' => TRUE,
          '#alt' => $this->getSetting('image_alt'),
          '#title' => $this->getSetting('image_title'),
          '#href' => $url,
          '#cache' => array(
            'tags' => $cache_tags,
          ),
        );
        break;

      case 'image':
        // @todo add cache tags for the source_image_file??
        // Get source image from an image field.
        foreach ($items as $delta => $item) {
          $elements[$delta] = array(
            '#theme' => 'textimage_formatter',
            '#style_name' => $this->getSetting('image_style'),
            '#text' => NULL,
            '#node' => $node,
            '#source_image_file' => $item->entity,
            '#force_hashed_filename' => TRUE,
            '#alt' => $this->getSetting('image_alt'),
            '#title' => $this->getSetting('image_title'),
            '#href' => $url,
            '#cache' => array(
              'tags' => $cache_tags,
            ),
          );
        }
        break;

    }

    return $elements;
  }

}
