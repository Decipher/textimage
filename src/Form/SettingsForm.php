<?php

/**
 * @file
 * Contains \Drupal\textimage\Form\SettingsForm.
 */

namespace Drupal\textimage\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\textimage\Plugin\TextimagePluginManager;
use Drupal\textimage\Plugin\TextimagePluginBaseInterface;
use Drupal\textimage\TextimageFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main Textimage settings admin form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Textimage factory.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $textimageFactory;

  /**
   * The font plugin manager.
   *
   * @var \Drupal\textimage\Plugin\TextimagePluginManager
   */
  protected $fontManager;

  /**
   * The background plugin manager.
   *
   * @var \Drupal\textimage\Plugin\TextimagePluginManager
   */
  protected $backgroundManager;

  /**
   * The color plugin manager.
   *
   * @var \Drupal\textimage\Plugin\TextimagePluginManager
   */
  protected $colorManager;

  /**
   * The Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs the class for Textimage settings form.
   *
   * @param \Drupal\textimage\TextimageFactory $textimage_factory
   *   The Textimage factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $font_plugin_manager
   *   The font plugin manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $background_plugin_manager
   *   The background images plugin manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $color_plugin_manager
   *   The color plugin manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $image_factory
   *   The Image factory.
   */
  public function __construct(TextimageFactory $textimage_factory, ConfigFactoryInterface $config_factory, TextimagePluginManager $font_plugin_manager, TextimagePluginManager $background_plugin_manager, TextimagePluginManager $color_plugin_manager, ImageFactory $image_factory) {
    parent::__construct($config_factory);
    $this->textimageFactory = $textimage_factory;
    $this->fontManager = $font_plugin_manager;
    $this->backgroundManager = $background_plugin_manager;
    $this->colorManager = $color_plugin_manager;
    $this->imageFactory = $image_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('textimage.factory'),
      $container->get('config.factory'),
      $container->get('plugin.manager.textimage.font'),
      $container->get('plugin.manager.textimage.background'),
      $container->get('plugin.manager.textimage.color'),
      $container->get('image.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'textimage_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['textimage.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('textimage.settings');

    $ajaxing = (bool) $form_state->getValues();

    // Font plugin.
    $font_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'font', 'plugin_id']) : $config->get('font.plugin_id');
    $font_plugin = $this->fontManager->getPlugin($font_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'font', 'plugin_settings'])) {
      $font_plugin->setConfiguration($form_state->getValue(['settings', 'font', 'plugin_settings']));
    }

    // Background plugin.
    $background_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'background', 'plugin_id']) : $config->get('background.plugin_id');
    $background_plugin = $this->backgroundManager->getPlugin($background_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'background', 'plugin_settings'])) {
      $background_plugin->setConfiguration($form_state->getValue(['settings', 'background', 'plugin_settings']));
    }

    // Color plugin.
    $color_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'color', 'plugin_id']) : $config->get('color.plugin_id');
    $color_plugin = $this->colorManager->getPlugin($color_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'color', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color', 'plugin_settings']));
    }

    // AJAX messages
    $form['ajax_messages'] = array(
      '#type' => 'container',
      '#attributes' => [
        'id' => 'textimage-ajax-messages',
      ],
    );

    // Main part of settings form.
    $form['settings'] = array(
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'textimage-settings-main',
      ],
    );

    $form['settings']['main'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Main settings'),
    );

    // Default image file format/extension.
    $extensions = $this->imageFactory->getSupportedExtensions();
    $options = array_combine($extensions, $extensions);
    $form['settings']['main']['default_extension'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Default image file extension'),
      '#default_value' => $config->get('default_extension'),
      '#required' => TRUE,
      '#description' => $this->t('Select the default extension of the image files produced by Textimage. This can be overridden by image style effects that specifiy a format conversion like e.g. <em>Convert</em> or <em>Textimage Background</em>. This setting does not affect image derivatives created by the Image module.'),
    );

    $ajax_settings = ['callback' => [$this, 'processAjax']];

    // Fonts.
    $form['settings']['font'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Fonts manager'),
      '#tree' => TRUE,
    );
    $form['settings']['font']['plugin_id'] = array(
      '#type' => 'radios',
      '#options' => $this->fontManager->getPluginOptions(),
      '#default_value' => $font_plugin->getPluginId(),
      '#required' => TRUE,
      '#ajax'  => $ajax_settings,
    );
    $form['settings']['font']['plugin_settings'] = $font_plugin->buildConfigurationForm(array(), $form_state, $ajax_settings);

    // Default font.
    $form['settings']['font']['default_font_name'] = $font_plugin->selectionElement(array(
      '#title' => $this->t('Default font'),
      '#description' => $this->t('Select the default font to be used by Textimage.'),
      '#default_value' => $config->get('default_font.name'),
    ));

    // Background images.
    $form['settings']['background'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Background images manager'),
      '#tree' => TRUE,
    );
    $form['settings']['background']['plugin_id'] = array(
      '#type'    => 'radios',
      '#options' => $this->backgroundManager->getPluginOptions(),
      '#default_value' => $background_plugin->getPluginId(),
      '#required'    => TRUE,
      '#ajax'  => $ajax_settings,
    );
    $form['settings']['background']['plugin_settings'] = $background_plugin->buildConfigurationForm(array(), $form_state, $ajax_settings);

    // Color.
    $form['settings']['color'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Color manager'),
      '#tree' => TRUE,
    );
    $form['settings']['color']['plugin_id'] = array(
      '#type' => 'radios',
      '#options' => $this->colorManager->getPluginOptions(),
      '#default_value' => $color_plugin->getPluginId(),
      '#required' => TRUE,
      '#ajax'  => $ajax_settings,
    );
    $form['settings']['color']['plugin_settings'] = $color_plugin->buildConfigurationForm(array(), $form_state, $ajax_settings);

    // URL generation.
    $form['settings']['url_generation'] = array(
      '#type' => 'details',
      '#title' => $this->t('URL generation'),
      '#open' => TRUE,
    );
    $form['settings']['url_generation']['enabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('When selected, direct generation of Textimage images is enabled for users having the \'Generate Textimage URL derivatives\' permission.'),
      '#default_value' => $config->get('url_generation.enabled'),
    );
    $form['settings']['url_generation']['text_separator'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Text separator'),
      '#maxlength' => 5,
      '#required' => TRUE,
      '#description' => $this->t('Indicate the sequence of characters to be used to split the URL text string in separate strings. Each string will be consumed by a \'Textimage Text\' effect in the sequence specified within the image style. Note that slashes \'/\' and plus \'+\' characters are not allowed.'),
      '#default_value' => $config->get('url_generation.text_separator'),
    );

    // Maintenance.
    $form['settings']['maintenance'] = array(
      '#type' => 'details',
      '#title' => $this->t('Maintenance'),
    );
    $form['settings']['maintenance']['debug'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Display debugging information'),
      '#default_value' => $config->get('debug'),
      '#description' => $this->t('Logs Textimage debug messages and shows them to users with the \'%permission\' permissions.', array(
        '%permission' => implode(', ', [
          $this->t('Administer site configuration'),
          $this->t('Administer image styles'),
        ])
      )),
    );
    $form['settings']['maintenance']['flush_all_label'] = [
      '#markup' => $this->t('Remove all image files generated via Textimage, flush all the Textimage image styles, and clear all image entries cached.') . '<br/>',
    ];
    $form['settings']['maintenance']['flush_all'] = array(
      '#type' => 'submit',
      '#name' => 'flush_all',
      '#value' => $this->t('Cleanup Textimage'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (preg_match('/[+\/]/', $form_state->getValue(['settings', 'url_generation', 'text_separator']))) {
      $form_state->setErrorByName('settings][url_generation][text_separator', $this->t('Invalid characters specified for the text separator.'));
    };
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('textimage.settings');

    // Redirect to cleanup if required.
    if ($form_state->getTriggeringElement()['#name'] == 'flush_all') {
      $form_state->setRedirect('textimage.flush_all');
      return;
    }

    // Main settings.
    $config
      ->set('default_extension', $form_state->getValue(['settings', 'main', 'default_extension']));

    // Font plugin.
    $font_plugin = $this->fontManager->getPlugin($form_state->getValue(['settings', 'font', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'font', 'plugin_settings'])) {
      $font_plugin->setConfiguration($form_state->getValue(['settings', 'font', 'plugin_settings']));
    }
    $config
      ->set('font.plugin_id', $font_plugin->getPluginId())
      ->set('font.plugin_settings.' . $font_plugin->getPluginId(), $font_plugin->getConfiguration());

    // Default font.
    $config
      ->set('default_font.name', $form_state->getValue(['settings', 'font', 'default_font_name']))
      ->set('default_font.uri', $font_plugin->getUri($form_state->getValue(['settings', 'font', 'default_font_name'])));

    // Background plugin.
    $background_plugin = $this->backgroundManager->getPlugin($form_state->getValue(['settings', 'background', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'background', 'plugin_settings'])) {
      $background_plugin->setConfiguration($form_state->getValue(['settings', 'background', 'plugin_settings']));
    }
    $config
      ->set('background.plugin_id', $background_plugin->getPluginId())
      ->set('background.plugin_settings.' . $background_plugin->getPluginId(), $background_plugin->getConfiguration());

    // Color plugin.
    $color_plugin = $this->colorManager->getPlugin($form_state->getValue(['settings', 'color', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'color', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color', 'plugin_settings']));
    }
    $config
      ->set('color.plugin_id', $color_plugin->getPluginId())
      ->set('color.plugin_settings.' . $color_plugin->getPluginId(), $color_plugin->getConfiguration());

    // URL generation.
    $config
      ->set('url_generation.enabled', $form_state->getValue(['settings', 'url_generation', 'enabled']))
      ->set('url_generation.text_separator', $form_state->getValue(['settings', 'url_generation', 'text_separator']));

    // Maintenance.
    $config
      ->set('debug', $form_state->getValue(['settings', 'maintenance', 'debug']));

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback.
   */
  public function processAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $status_messages = array('#type' => 'status_messages');
    $response->addCommand(new HtmlCommand('#textimage-ajax-messages', $status_messages));
    $response->addCommand(new HtmlCommand('#textimage-settings-main', $form['settings']));
    return $response;
  }

}
