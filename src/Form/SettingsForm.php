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
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
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
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

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
   * Constructs the class for Textimage settings form.
   *
   * @param \Drupal\textimage\TextimageFactory $textimage_factory
   *   The Textimage factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $font_plugin_manager
   *   The font plugin manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $background_plugin_manager
   *   The background images plugin manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $color_plugin_manager
   *   The color plugin manager.
   */
  public function __construct(TextimageFactory $textimage_factory, ConfigFactoryInterface $config_factory, StreamWrapperManager $stream_wrapper_manager, TextimagePluginManager $font_plugin_manager, TextimagePluginManager $background_plugin_manager, TextimagePluginManager $color_plugin_manager) {
    parent::__construct($config_factory);
    $this->textimageFactory = $textimage_factory;
    $this->fontManager = $font_plugin_manager;
    $this->backgroundManager = $background_plugin_manager;
    $this->colorManager = $color_plugin_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('textimage.factory'),
      $container->get('config.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('plugin.manager.textimage.font'),
      $container->get('plugin.manager.textimage.background'),
      $container->get('plugin.manager.textimage.color')
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
  public function buildForm(array $form, FormStateInterface $form_state) {

    $ajaxing = (bool) $form_state->getValues();

    // Font plugin.
    $font_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'font', 'plugin_id']) : $this->config('textimage.settings')->get('font.plugin_id');
    $font_plugin = $this->fontManager->getPlugin($font_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'font', 'plugin_settings'])) {
      $font_plugin->setConfiguration($form_state->getValue(['settings', 'font', 'plugin_settings']));
    }

    // Background plugin.
    $background_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'background', 'plugin_id']) : $this->config('textimage.settings')->get('background.plugin_id');
    $background_plugin = $this->backgroundManager->getPlugin($background_plugin_id);
    if ($ajaxing && $form_state->hasValue(['settings', 'background', 'plugin_settings'])) {
      $background_plugin->setConfiguration($form_state->getValue(['settings', 'background', 'plugin_settings']));
    }

    // Color plugin.
    $color_plugin_id = $ajaxing ? $form_state->getValue(['settings', 'color', 'plugin_id']) : $this->config('textimage.settings')->get('color.plugin_id');
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

    // Main Textimage store location.
    $scheme_options = $this->streamWrapperManager->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $default_scheme = $this->config('textimage.settings')->get('store_scheme');
    $default_scheme = isset($scheme_options[$default_scheme]) ? $default_scheme : 'public';
    $form['settings']['textimage_store'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Textimage store location'),
    );
    $form['settings']['textimage_store']['store_scheme'] = array(
      '#type' => 'radios',
      '#options' => $scheme_options,
      '#title' => $this->t('Scheme'),
      '#description' => $this->t('Select where the main Textimage file structure should be stored. It is recommended to keep it in the <strong>private</strong> file storage area.'),
      '#default_value' => $default_scheme,
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
      '#default_value' => $this->config('textimage.settings')->get('default_font.name'),
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
      '#default_value' => $this->config('textimage.settings')->get('url_generation.enabled'),
    );
    $form['settings']['url_generation']['text_separator'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Text separator'),
      '#maxlength' => 5,
      '#required' => TRUE,
      '#description' => $this->t('Indicate the sequence of characters to be used to split the URL text string in separate strings. Each string will be consumed by a \'Textimage Text\' effect in the sequence specified within the image style. Note that slashes \'/\' and plus \'+\' characters are not allowed.'),
      '#default_value' => $this->config('textimage.settings')->get('url_generation.text_separator'),
    );

    // Maintenance.
    $form['settings']['maintenance'] = array(
      '#type' => 'details',
      '#title' => $this->t('Maintenance'),
      '#description' => $this->t('Remove all image files generated via Textimage, flush all the Textimage image styles, and clear all image entries cached and stored in the database.'),
    );
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
    // Redirect to cleanup if required.
    if ($form_state->getTriggeringElement()['#name'] == 'flush_all') {
      $form_state->setRedirect('textimage.flush_all');
      return;
    }

    // Overall module flush if storage scheme gets changed.
    if ($form_state->getValue(['settings', 'textimage_store', 'store_scheme']) != $this->config('textimage.settings')->get('store_scheme')) {
      $this->textimageFactory->flushAll();
    }

    // Main Textimage store location.
    $this->config('textimage.settings')->set('store_scheme', $form_state->getValue(['settings', 'textimage_store', 'store_scheme']));

    // Font plugin.
    $font_plugin = $this->fontManager->getPlugin($form_state->getValue(['settings', 'font', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'font', 'plugin_settings'])) {
      $font_plugin->setConfiguration($form_state->getValue(['settings', 'font', 'plugin_settings']));
    }
    $this->config('textimage.settings')
      ->set('font.plugin_id', $font_plugin->getPluginId())
      ->set('font.plugin_settings.' . $font_plugin->getPluginId(), $font_plugin->getConfiguration());

    // Default font.
    $this->config('textimage.settings')
      ->set('default_font.name', $form_state->getValue(['settings', 'font', 'default_font_name']))
      ->set('default_font.uri', $font_plugin->getUri($form_state->getValue(['settings', 'font', 'default_font_name'])));

    // Background plugin.
    $background_plugin = $this->backgroundManager->getPlugin($form_state->getValue(['settings', 'background', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'background', 'plugin_settings'])) {
      $background_plugin->setConfiguration($form_state->getValue(['settings', 'background', 'plugin_settings']));
    }
    $this->config('textimage.settings')
      ->set('background.plugin_id', $background_plugin->getPluginId())
      ->set('background.plugin_settings.' . $background_plugin->getPluginId(), $background_plugin->getConfiguration());

    // Color plugin.
    $color_plugin = $this->colorManager->getPlugin($form_state->getValue(['settings', 'color', 'plugin_id']));
    if ($form_state->hasValue(['settings', 'color', 'plugin_settings'])) {
      $color_plugin->setConfiguration($form_state->getValue(['settings', 'color', 'plugin_settings']));
    }
    $this->config('textimage.settings')
      ->set('color.plugin_id', $color_plugin->getPluginId())
      ->set('color.plugin_settings.' . $color_plugin->getPluginId(), $color_plugin->getConfiguration());

    // URL generation.
    $this->config('textimage.settings')
      ->set('url_generation.enabled', $form_state->getValue(['settings', 'url_generation', 'enabled']))
      ->set('url_generation.text_separator', $form_state->getValue(['settings', 'url_generation', 'text_separator']));

    $this->config('textimage.settings')->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback.
   */
  public function processAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $status_messages = array('#theme' => 'status_messages');
    $response->addCommand(new HtmlCommand('#textimage-ajax-messages', drupal_render($status_messages))); // @todo drupal_render in ajax may be dropped see #2347469
    $response->addCommand(new HtmlCommand('#textimage-settings-main', drupal_render($form['settings']))); // @todo drupal_render in ajax may be dropped see #2347469
    return $response;
  }

}
