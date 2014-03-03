<?php

/**
 * @file
 * Contains \Drupal\textimage\Plugin\TextimagePluginManager.
 */

namespace Drupal\textimage\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for Textimage plugins.
 */
class TextimagePluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   *
   * @param string $type
   *   The plugin type, for example Font.
   */
  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, LanguageManager $language_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct("Plugin/textimage/$type", $namespaces, $module_handler);
    $this->alterInfo('textimage_' . $type . '_plugin_info');
    $this->setCacheBackend($cache_backend, $language_manager, 'textimage_' . $type . '_plugins');
    $this->defaults += array(
      'plugin_type' => $type,
    );
  }

  public function getType() {
    return $this->defaults['plugin_type'];
  }

  public function getPlugin($plugin_id = NULL) {
    $plugin_id = $plugin_id ? $plugin_id : \Drupal::config('textimage.settings')->get($this->getType() . '.plugin_id');  // @todo inject
    $plugins = $this->getAvailablePlugins();

    // Check if plugin is available.
    if (!isset($plugins[$plugin_id]) || !class_exists($plugins[$plugin_id]['class'])) {
      _textimage_diag(
        t(
          "@type handling plugin '@plugin_id' is no longer available.",
          array(
            '@type' => $this->getType(),
            '@plugin_id' => $plugin_id,
          )
        ),
        WATCHDOG_ERROR,
        __FUNCTION__
      );
    }

    // Return plugin instance or base Textimage plugin if not available.
    if ($plugin_id) {
      return $this->createInstance($plugin_id, array('plugin_type' => $this->getType()));
    }
    else {
      return $this->createInstance('textimage', array('plugin_type' => $this->getType()));
    }
  }

  /**
   * Gets a list of available plugins.
   *
   * @return array
   *   An array with the plugin ids as keys and the definitions as values.
   */
  public function getAvailablePlugins() {
    $plugins = $this->getDefinitions();
    $output = array();
    foreach ($plugins as $id => $definition) {
      // Only allow plugins that are available.
      if (call_user_func($definition['class'] . '::isAvailable')) {
        $output[$id] = $definition;
      }
    }
    return $output;
  }

  public function getPluginOptions() {
    $options = array();
    foreach ($this->getAvailablePlugins() as $plugin) {
      $options[$plugin['id']] = '<b>' . $plugin['short_title'] . '</b> - ' . $plugin['help'];
    }
    return $options;
  }

}
