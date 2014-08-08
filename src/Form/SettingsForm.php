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
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $font_plugin_factory
   *   The font plugin factory.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $background_plugin_factory
   *   The background images plugin factory.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $color_plugin_factory
   *   The color plugin factory.
   */
  public function __construct(TextimageFactory $textimage_factory, ConfigFactoryInterface $config_factory, TextimagePluginManager $font_plugin_factory, TextimagePluginManager $background_plugin_factory, TextimagePluginManager $color_plugin_factory) {
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

    $v = isset($form_state['values']) ? $form_state['values'] : NULL;
    $ajaxing = $v ? TRUE : FALSE;

    // Loops through plugin factory to get plugins.
    foreach ($this->pluginFactory as $type => $pluginFactory) {
      $plugin_id = $ajaxing ? $v[$type]['plugin_id'] : $this->config->get($type . '.plugin_id');
      $plugin[$type] = $this->pluginFactory[$type]->getPlugin($plugin_id);
      if ($ajaxing && isset($v[$type]['plugin_settings'])) {
        $plugin[$type]->setConfiguration($v[$type]['plugin_settings']);
      }
    }

    // Main Textimage store location.
    $scheme_options = array();
    foreach (file_get_stream_wrappers(STREAM_WRAPPERS_WRITE_VISIBLE) as $scheme => $stream_wrapper) {
      $scheme_options[$scheme] = $stream_wrapper['name'];
    }
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
      '#title' => $this->t('Fonts'),
      '#tree' => TRUE,
    );
    $form['font'] += $this->buildPluginForm($form, $form_state, $plugin['font'],
      array(
        '#title'   => $this->t('Fonts manager'),
      )
    );

    // Default font.
    $form['font'] += $plugin['font']->selectionElement('default_font_name', array(
      '#title' => $this->t('Default font'),
      '#description' => $this->t('Select the default font to be used by Textimage.'),
    ));

    // Background images.
    $form['background'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Background images'),
      '#tree' => TRUE,
    );
    $form['background'] += $this->buildPluginForm($form, $form_state, $plugin['background'],
      array(
        '#title'   => $this->t('Background images manager'),
      )
    );

    // Color.
    $form['color'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Colors'),
      '#tree' => TRUE,
    );
    $form['color'] += $this->buildPluginForm($form, $form_state, $plugin['color'],
      array(
        '#title'   => $this->t('Color manager'),
      )
    );

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
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   * @param \Drupal\textimage\Plugin\TextimagePluginManager $plugin
   *   A Textimage plugin object.
   * @param array $options
   *   An associative array of options.
   *
   * @return array
   *   The form structure.
   */
  protected function buildPluginForm(array $form, FormStateInterface $form_state, TextimagePluginBaseInterface $plugin, array $options) {
    $type = $plugin->getType();
    $ajax_settings = array(
      'callback' => array($this, 'processAjax'),
    );
    $element['plugin_id'] = array(
      '#type'    => 'radios',
      '#title'   => $options['#title'],
      '#options' => $this->pluginFactory[$type]->getPluginOptions(),
      '#default_value' => $plugin->getPluginId(),
      '#required'    => TRUE,
      '#ajax'  => $ajax_settings,
    );
    $element['plugin_settings'] = $plugin->configurationForm($form, $form_state, array('#ajax' => $ajax_settings));
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Redirect to cleanup if required.
    if ($form_state['triggering_element']['#name'] == 'flush_all') {
      $form_state->setRedirect('textimage.flush_all');
      return;
    }

    // Overall module flush if storage scheme gets changed.
    if ($form_state['values']['store_scheme'] != $this->config->get('store_scheme')) {
      $this->textimageFactory->flushAll();
    }

    // Main Textimage store location.
    $this->config->set('store_scheme', $form_state['values']['store_scheme']);

    // Loops through plugin factory to save settings.
    foreach ($this->pluginFactory as $type => $pluginFactory) {
      $plugin = $pluginFactory->getPlugin($form_state['values'][$type]['plugin_id']);
      if (isset($form_state['values'][$type]['plugin_settings'])) {
        $plugin->setConfiguration($form_state['values'][$type]['plugin_settings']);
      }
      $this->config
        ->set($type . '.plugin_id', $plugin->getPluginId())
        ->set($type . '.plugin_settings.' . $plugin->getPluginId(), $plugin->getConfiguration());
      if ($type == 'font' && !empty($form_state['values']['font']['default_font_name'])) {
        // Default font.
        $this->config
          ->set('default_font.name', $form_state['values']['font']['default_font_name'])
          ->set('default_font.uri', $plugin->getUri($form_state['values']['font']['default_font_name']));
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
