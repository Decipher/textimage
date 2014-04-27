<?php

/**
 * @file
 * Contains \Drupal\textimage\TextimageFactory.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\field\FieldInfo;

/**
 * Provides a factory for Textimage.
 */
class TextimageFactory {

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\DatabaseLockBackend
   */
  protected $lock;

  /**
   * The token resolution service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The textimage cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The field information service.
   *
   * @var \Drupal\field\FieldInfo
   */
  protected $fieldInfo;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new TextimageFactory object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   the config factory
   * @param \Drupal\Core\Lock\DatabaseLockBackend $lock_service
   *   the lock service
   * @param \Drupal\Core\Utility\Token $token_service
   *   the token resolution service
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   the textimage cache service
   * @param \Drupal\field\FieldInfo $field_info
   *   the field information service
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   the current user
   */
  public function __construct(ConfigFactoryInterface $config_factory, DatabaseLockBackend $lock_service, Token $token_service, CacheBackendInterface $cache_service, FieldInfo $field_info, AccountInterface $current_user) {
    $this->config = $config_factory->get('textimage.settings');
    $this->lock = $lock_service;
    $this->token = $token_service;
    $this->cache = $cache_service;
    $this->fieldInfo = $field_info;
    $this->currentUser = $current_user;
  }

  /**
   * Get a Textimage, building the image if necessary.
   */
  public function getTextimage() {
    return new Textimage($this, $this->lock, $this->cache);
  }

  /**
   * Process text string, detokenise and apply case conversion.
   */
  public function processTextString($text, $case_format, $node = NULL, $source_image_file = NULL) {

    // Replace any tokens in text with run-time values.
    $text = $this->token->replace(
      $text,
      array(
        'user' => $this->currentUser,
        'node' => $node,
        'file' => $source_image_file,
      )
    );
    // Convert case, if requested.
    switch ($case_format) {
      case 'upper':
        return Unicode::strtoupper($text);

      case 'lower':
        return Unicode::strtolower($text);

      case 'ucfirst':
        return Unicode::ucfirst($text);

      case 'ucwords':
        return Unicode::ucwords($text);

      default:
        return $text;

    }
  }

  /**
   * Get an Imager state variable.
   *
   * @param string $variable
   *   state variable
   *
   * @return mixed
   *   returned variable, NULL if undefined
   */
  public function getState($variable = NULL) {
    if ($variable) {
      return $this->setState($variable);
    }
    return NULL;
  }

  /**
   * Set an Imager state variable.
   *
   * @param string $variable
   *   state variable
   * @param mixed $value
   *   value to set, or NULL to return current value
   *
   * @return mixed
   *   variable value
   */
  public function setState($variable = NULL, $value = NULL) {
    static $keys;

    if (!isset($keys) or !$variable) {
      $keys = array();
    }

    if ($variable) {
      if ($value) {
        $keys[$variable] = $value;
        return $value;
      }
      else {
        return isset($keys[$variable]) ? $keys[$variable] : NULL;
      }
    }
  }

  /**
   * Cleanup Textimage.
   *
   * This will remove all image files generated via Textimage, flush all
   * the image styles, clear all cache and all store entries on the db.
   */
  public function flushAll() {
  /*  foreach (image_styles() as $style) {
      if (...:isTextimage($style)) {
        image_style_flush($style);
      }
    }*/
    if (file_exists('public://textimage')) {
      file_unmanaged_delete_recursive('public://textimage');  // @todo temp
    }
    if (file_exists('private://textimage')) {
      file_unmanaged_delete_recursive('private://textimage');  // @todo temp
    }
    if (file_exists($this->getStorePath('unstyled_hashed'))) {
      file_unmanaged_delete_recursive($this->getStorePath('unstyled_hashed'));
    }
    if (file_exists($this->getStorePath('uncached'))) {
      file_unmanaged_delete_recursive($this->getStorePath('uncached'));
    }
    $this->cache->deleteAll();
    db_truncate('textimage_store')->execute();
    _textimage_diag(t('All Textimage images were removed.'), WATCHDOG_NOTICE);
  }

  /**
   * Return a path within the textimage_store structure.
   */
  public function getStorePath($path) {
    return $this->config->get('store_scheme') . '://textimage_store/' . $path;
  }

  /**
   * Textimage tokens replacement.
   *
   * @param string $key
   *   The Textimage token key within the main token [textimage:key:...].
   *   Key can take 'uri' or 'url' values.
   * @param array $tokens
   *   The tokens to resolve.
   * @param object $node
   *   The node for which to resolve the tokens.
   *
   * @return array
   *   An array of token replacements.
   */
  public function processTokens($key, $tokens, $node) {

    // Need to avoid endless loops, that would occur if there are
    // circular references in the tokens. Set static variables for
    // the nesting level and the stack of fields accessed so far.
    static $nesting_level;
    static $field_stack;
    if (!isset($nesting_level)) {
      $nesting_level = 0;
      $field_stack = array();
    }
    else {
      $nesting_level++;
    }

    // Get tokens specific for the required key.
    $sub_tokens = $this->token->findWithPrefix($tokens, $key);

    // Return immediately if none, or no node.
    if (empty($sub_tokens) || !$node) {
      $this->rollbackStack($nesting_level, $field_stack);
      return array();
    }

    // Determine the callback function.
    switch ($key) {
      case 'uri':
        $callback_function = 'getUri';
        break;

      case 'url':
        $callback_function = 'getUrl';
        break;

    }

    // Loops through the tokens to resolve.
    $replacements = array();
    foreach ($sub_tokens as $sub_token => $original) {

      // Clear current nesting level field stack.
      unset($field_stack[$nesting_level]);

      // Get token elements.
      $sub_token_array = explode(':', $sub_token);

      // Get requested field name, continue if missing.
      $field_name = isset($sub_token_array[0]) ? $sub_token_array[0] : NULL;
      if (!$field_name) {
        continue;
      }

      // Check for recursion, i.e. the field is already engaged in a
      // token resolution. Throw a TextimageTokenException in case.
      if (in_array($field_name, $field_stack)) {
        $this->rollbackStack($nesting_level, $field_stack);
        throw new TextimageTokenException($original);
      }

      // Set current requested field in the field stack.
      $field_stack[$nesting_level] = $field_name;

      // Get requested display mode, default to 'default'.
      $display_mode = isset($sub_token_array[1]) ? $sub_token_array[1] : 'default';

      // Get requested sequence, default to NULL.
      $index = isset($sub_token_array[2]) ? $sub_token_array[2] : NULL;

      // Get general field info, continue if missing.
      $field_info = $this->fieldInfo->getField('node', $field_name);
      if (!$field_info) {
        continue;
      }

      // Get node (bundle) dependent field info, continue if missing.
      $node_type = $node->getType();
      $instance_info = $this->fieldInfo->getInstance('node', $node_type, $field_name);
      if (!$instance_info) {
        continue;
      }

      // Get info on component providing formatting, continue if missing.
      $entity_display = entity_get_display('node', $node_type, $display_mode);
      if (!$entity_display) {
        continue;
      }
      $entity_display_component = $entity_display->getComponent($field_name);
      if (empty($entity_display_component['type'])) {
        continue;
      }

      // At this point, if Textimage is providing field formatting for the
      // current field, we can proceed accessing the data needed to resolve
      // the token.
      if ($entity_display_component['type'] == 'textimage') {

        // Get the image style used for the field formatting.
        $image_style = isset($entity_display_component['settings']['image_style']) ? $entity_display_component['settings']['image_style'] : NULL;
        if (!$image_style) {
          continue;
        }

        // Get the field items.
        $items = $node->get($field_name);

        // Invoke Textimage API functions to return the token value requested.
        if ($field_info->module == 'text') {
          // Text field. Get sanitized text items and return a single image.
          $text = $this->getTextFieldText($items);
          try {
            $replacements[$original] = $this->getTextimage()
              ->styleByName($image_style)
              ->node($node)
              ->process($text)
              ->$callback_function();
          }
          catch (TextimageTokenException $e) {
            // Callback ended up in circular loop, mark the failing token.
            $replacements[$original] = str_replace('textimage', 'void-textimage', $original);
            if ($nesting_level > 0) {
              // Returns up in the nesting of iteration with the failing token.
              $this->rollbackStack($nesting_level, $field_stack);
              throw new TextimageTokenException($e->getToken());
            }
            else {
              // Inform about the token failure.
              $msg = t("Textimage token @token in node '@node_title' can not be resolved (circular reference). Remove the token to avoid this message.",
                array(
                  '@token' => $original,
                  '@node_title' => $node->getTitle(),
                )
              );
              _textimage_diag($msg, WATCHDOG_WARNING);
            }
          }
        }
        elseif ($field_info->module == 'image') {
          // Image field. Get a separate Textimage from each of the images
          // in the field.
          try {
            $ret = array();
            foreach ($items as $delta => $item) {
              // Get source image from the image field item.
              $ret[] = $this->getTextimage()
                ->styleByName($image_style)
                ->node($node)
                ->sourceImageFile($item->entity)
                ->process(NULL)
                ->$callback_function();
            }
            // Return a single URI/URL if requested, or a comma separated
            // list of all the URIs/URLs generated.
            if (!is_null($index) && isset($ret[$index])) {
              $replacements[$original] = $ret[$index];
            }
            else {
              $replacements[$original] = implode(',', $ret);
            }
          }
          catch (TextimageTokenException $e) {
            // Callback ended up in circular loop, mark the failing token.
            $replacements[$original] = str_replace('textimage', 'void-textimage', $original);
            if ($nesting_level > 0) {
              // Returns up in the nesting of iteration with the failing token.
              $this->rollbackStack($nesting_level, $field_stack);
              throw new TextimageTokenException($e->getToken());
            }
            else {
              // Inform about the token failure.
              $msg = t("Textimage token @token in node '@node_title' can not be resolved (circular reference). Remove the token to avoid this message.",
                array(
                  '@token' => $original,
                  '@node_title' => $node->getTitle(),
                )
              );
              _textimage_diag($msg, WATCHDOG_WARNING);
            }
          }
        }
      }
    }

    // Return to previous iteration.
    $this->rollbackStack($nesting_level, $field_stack);
    return $replacements;
  }

  /**
   * Helper method to rollback nesting static variables in processTokens.
   */
  protected function rollbackStack(&$nesting_level, &$field_stack) {
    if ($nesting_level) {
      unset($field_stack[$nesting_level]);
      $nesting_level--;
    }
    else {
      $nesting_level = NULL;
    }
  }

  /**
   * Retrieves text from a Text field.
   *
   * Text gets sanitized for use within Textimage: HTML tags are
   * stripped.
   *
   * @param Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   *
   * @return array
   *   An array of sanitized text items.
   */
  public function getTextFieldText(FieldItemListInterface $items) {
    $text = array();
    foreach ($items as $delta => $item) {
      $value = $item->getValue();
      $text[] = strip_tags($value['value']);
    }
    return $text;
  }

}
