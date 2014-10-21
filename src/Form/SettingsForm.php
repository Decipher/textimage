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
   * An array of Textimage plugin factories.
   *
   * @var array
   */
  protected $pluginFactory = array();

  /**
   * Constructs the class for Textimage settings form.
   *
   * @param \Drupal\textimage\TextimageFactory $textimage_factory
   *   The Textimage factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $font_plugin_factory
   *   The font plugin factory.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $background_plugin_factory
   *   The background images plugin factory.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $color_plugin_factory
   *   The color plugin factory.
   */
  public function __construct(TextimageFactory $textimage_factory, ConfigFactoryInterface $config_factory, StreamWrapperManager $stream_wrapper_manager, TextimagePluginManager $font_plugin_factory, TextimagePluginManager $background_plugin_factory, TextimagePluginManager $color_plugin_factory) {
    parent::__construct($config_factory);
    $this->textimageFactory = $textimage_factory;
    // Loops through the function args to build the array of Textimage
    // plugin factories.
    foreach (func_get_args() as $arg) {
      if ($arg instanceof TextimagePluginManager) {
        $this->pluginFactory[$arg->getType()] = $arg;
      }
    }
    $this->config = $this->config('textimage.settings');
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

    $plugin = array();
    $ajaxing = (bool) $form_state->getValues();

    // Loops through plugin factory to get plugins.
    foreach ($this->pluginFactory as $type => $pluginFactory) {
      $plugin_id = $ajaxing ? $form_state->getValue(array($type, 'plugin_id')) : $this->config->get($type . '.plugin_id');
      $plugin[$type] = $this->pluginFactory[$type]->getPlugin($plugin_id);
      if ($ajaxing && $form_state->hasValue(array($type, 'plugin_settings'))) {
        $plugin[$type]->setConfiguration($form_state->getValue(array($type, 'plugin_settings')));
      }
    }

    // Main Textimage store location.
    $scheme_options = $this->streamWrapperManager->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $default_scheme = $this->config->get('store_scheme');
    $default_scheme = isset($scheme_options[$default_scheme]) ? $default_scheme : 'public';
    $form['textimage_store'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Textimage store location'),
    );
    $form['textimage_store']['store_scheme'] = array(
      '#type' => 'radios',
      '#options' => $scheme_options,
      '#title' => $this->t('Scheme'),
      '#description' => $this->t('Select where the main Textimage file structure should be stored. It is recommended to keep it in the <strong>private</strong> file storage area.'),
      '#default_value' => $default_scheme,
    );

    // Fonts.
    $form['font'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Fonts manager'),
      '#tree' => TRUE,
    );
    $form['font'] += $this->buildPluginForm($form_state, $plugin['font']);

    // Default font.
    $form['font'] += $plugin['font']->selectionElement('default_font_name', array(
      '#title' => $this->t('Default font'),
      '#description' => $this->t('Select the default font to be used by Textimage.'),
    ));

    // Background images.
    $form['background'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Background images manager'),
      '#tree' => TRUE,
    );
    $form['background'] += $this->buildPluginForm($form_state, $plugin['background']);

    // Color.
    $form['color'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Color manager'),
      '#tree' => TRUE,
    );
    $form['color'] += $this->buildPluginForm($form_state, $plugin['color']);

    // Maintenance.
    $form['maintenance'] = array(
      '#type' => 'details',
      '#title' => $this->t('Maintenance'),
      '#description' => t('Remove all image files generated via Textimage, flush all the Textimage image styles, and clear all image entries cached and stored in the database.'),
    );
    $form['maintenance']['flush_all'] = array(
      '#type' => 'submit',
      '#name' => 'flush_all',
      '#value' => $this->t('Cleanup Textimage'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds a portion of the form to capture plugin selection and settings.
   *
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $plugin
   *   A Textimage plugin object.
   *
   * @return array
   *   The form structure.
   */
  protected function buildPluginForm(FormStateInterface $form_state, TextimagePluginBaseInterface $plugin) {
    $type = $plugin->getType();
    $ajax_settings = ['callback' => [$this, 'processAjax']];
    $element['plugin_id'] = array(
      '#type'    => 'radios',
      '#options' => $this->pluginFactory[$type]->getPluginOptions(),
      '#default_value' => $plugin->getPluginId(),
      '#required'    => TRUE,
      '#ajax'  => $ajax_settings,
    );
    $element['plugin_settings'] = $plugin->buildConfigurationForm(array(), $form_state);
    $plugin->addConfigurationFormAjax($element['plugin_settings'], $ajax_settings); // @todo see this
    return $element;
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
    if ($form_state->getValue('store_scheme') != $this->config->get('store_scheme')) {
      $this->textimageFactory->flushAll();
    }

    // Main Textimage store location.
    $this->config->set('store_scheme', $form_state->getValue('store_scheme'));

    // Loops through plugin factory to save settings.
    foreach ($this->pluginFactory as $type => $pluginFactory) {
      $plugin = $pluginFactory->getPlugin($form_state->getValue(array($type, 'plugin_id')));
      if ($form_state->hasValue(array($type, 'plugin_settings'))) {
        $plugin->setConfiguration($form_state->getValue(array($type, 'plugin_settings')));
      }
      $this->config
        ->set($type . '.plugin_id', $plugin->getPluginId())
        ->set($type . '.plugin_settings.' . $plugin->getPluginId(), $plugin->getConfiguration());
      if ($type == 'font' && !$form_state->isValueEmpty(array('font', 'default_font_name'))) {
        // Default font.
        $this->config
          ->set('default_font.name', $form_state->getValue(array('font', 'default_font_name')))
          ->set('default_font.uri', $plugin->getUri($form_state->getValue(array('font', 'default_font_name'))));
      }
    }

    $this->config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback.
   */
  public function processAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $status_messages = array('#theme' => 'status_messages');
    $response->addCommand(new HtmlCommand('#console', drupal_render($status_messages)));
    $response->addCommand(new HtmlCommand('#textimage-settings', drupal_render($form)));
    return $response;
  }

}
