<?php

/**
 * @file
 * Contains \Drupal\textimage\TextimageFactory.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Utility\Token;
use Drupal\image\ImageEffectManager;
use Drupal\image\ImageStyleInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a factory for Textimage.
 */
class TextimageFactory {

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

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
   * The Textimage logger.
   *
   * @var \Psr\Log\LoggerInterface.
   */
  protected $logger;

  /**
   * The image effect manager service.
   *
   * @var \Drupal\image\ImageEffectManager
   */
  protected $imageEffectManager;

  /**
   * The stream wrapper manager service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * The Textimage cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new TextimageFactory object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Lock\DatabaseLockBackend $lock_service
   *   The lock service.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token resolution service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Textimage logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The Textimage cache service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageEffectManager $image_effect_manager
   *   The image effect manager service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ImageFactory $image_factory, DatabaseLockBackend $lock_service, Token $token_service, LoggerInterface $logger, CacheBackendInterface $cache_service, CacheTagsInvalidatorInterface $cache_tags_invalidator, AccountInterface $current_user, ImageEffectManager $image_effect_manager, StreamWrapperManager $stream_wrapper_manager, Connection $database) {
    $this->config = $config_factory->get('textimage.settings');
    $this->imageFactory = $image_factory;
    $this->lock = $lock_service;
    $this->token = $token_service;
    $this->logger = $logger;
    $this->cache = $cache_service;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->currentUser = $current_user;
    $this->imageEffectManager = $image_effect_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->database = $database;
  }

  /**
   * Returns the Textimage config service.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The Textimage cache service.
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Returns the Textimage cache service.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The Textimage cache service.
   */
  public function getCache() {
    return $this->cache;
  }

  /**
   * Returns the lock service.
   *
   * @return \Drupal\Core\Lock\DatabaseLockBackend
   *   The lock service.
   */
  public function getLock() {
    return $this->lock;
  }

  /**
   * Returns the image factory.
   *
   * @return \Drupal\Core\Image\ImageFactory
   *   The image factory.
   */
  public function getImageFactory() {
    return $this->imageFactory;
  }

  /**
   * Returns the current active database's master connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  public function getDatabase() {
    return $this->database;
  }

  /**
   * Returns the Textimage logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The Textimage logger.
   */
  public function getLogger() {
    return $this->logger;
  }

  /**
   * Gets a Textimage object.
   *
   * @return \Drupal\textimage\Textimage
   *   A new Textimage object.
   */
  public function getTextimage() {
    return new Textimage($this);
  }

  /**
   * Builds an image style from an array of effects.
   *
   * The runtime style object does not get saved. It is used to be
   * passed to ImageStyle::createDerivative() to build an image derivative.
   *
   * @param array $effects
   *   an array of image effects
   *
   * @return \Drupal\image\ImageStyleInterface
   *   an image style object
   */
  public function buildStyleFromEffects($effects) {
    $style = entity_create('image_style', array());
    foreach ($effects as $effect) {
      $effect_instance = $this->imageEffectManager->createInstance($effect['id']);
      $default_config = $effect_instance->defaultConfiguration();
      $effect['data'] = array_replace_recursive($default_config, $effect['data']);
      $style->addImageEffect($effect);
    }
    $style->getEffects()->sort();
    return $style;
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
   * Gets a Textimage state variable.
   *
   * @todo (core) remove when #1826362 (ImageStyle to be accessible from
   * ImageEffect plugins) is committed.
   *
   * @param string $variable
   *   State variable.
   *
   * @return mixed
   *   Returned variable, NULL if undefined.
   */
  public function getState($variable = NULL) {
    if ($variable) {
      return $this->setState($variable);
    }
    return NULL;
  }

  /**
   * Sets a Textimage state variable.
   *
   * @todo (core) remove when #1826362 (ImageStyle to be accessible from
   * ImageEffect plugins) is committed.
   *
   * @param string $variable
   *   State variable.
   * @param mixed $value
   *   Value to set, or NULL to return current value.
   *
   * @return mixed
   *   Property value.
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
   * Check if an image style is Textimage relevant.
   *
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to check.
   *
   * @return bool
   *   TRUE if style is Textimage relevant, otherwise FALSE
   */
  public function isTextimage(ImageStyleInterface $image_style) {
    return (bool) $image_style->getThirdPartySetting('textimage', 'is_relevant', FALSE);
  }

  /**
   * Gets an array of Textimage image styles suitable for select list options.
   *
   * @return
   *   Array of image styles both key and value are set to style name.
   */
  public function getTextimageStyleOptions() {
    $image_styles = entity_load_multiple('image_style');
    $options = array();
    foreach ($image_styles as $name => $image_style) {
      if ($this->isTextimage($image_style)) {
        $options[$name] = $image_style->label();
      }
    }
    if (empty($options)) {
      $options[''] = t('No defined styles');
    }
    return $options;
  }

  /**
   * Flushes Textimage style data.
   *
   * Clears immediate cache and all the image files associated.
   *
   * @param array $style
   *   the style being flushed
   */
  public function flushStyle($style) {
    // Clear style's cached images URI.
    $this->cacheTagsInvalidator->invalidateTags(['textimage_tiid', 'textimage_style:' . $style->id()]);
    // Clear hashed filename images.
    if (file_exists($directory = $this->getStorePath('styled_hashed/') . $style->id())) {
      file_unmanaged_delete_recursive($directory);
    }
    // Clear images, checking in all available schemes.
    $wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
    foreach ($wrappers as $wrapper => $wrapper_data) {
      if (file_exists($directory = $wrapper . '://textimage/' . $style->id())) {
        file_unmanaged_delete_recursive($directory);
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
    $image_styles = entity_load_multiple('image_style');
    foreach ($image_styles as $image_style) {
      if ($this->isTextimage($image_style)) {
        $image_style->flush();
      }
    }
    if (file_exists($directory = $this->getStorePath('unstyled_hashed'))) {
      file_unmanaged_delete_recursive($directory);
    }
    if (file_exists($directory = $this->getStorePath('uncached'))) {
      file_unmanaged_delete_recursive($directory);
    }
    $this->cache->deleteAll();
    $this->database->truncate('textimage_store')->execute();
    $this->logger->notice(t('All Textimage images were removed.'));
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
        $callback_method = 'getUri';
        break;

      case 'url':
        $callback_method = 'getUrl';
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

      // Get field info, continue if missing.
      if (!$field_info = $node->getFieldDefinition($field_name)) {
        continue;
      }

      // Get info on component providing formatting, continue if missing.
      $entity_display = entity_get_display('node', $node->getType(), $display_mode);
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
        if ($field_info->getFieldStorageDefinition()->getTypeProvider() == 'text') {
          // Text field. Get sanitized text items and return a single image.
          $text = $this->getTextFieldText($items);
          try {
            $replacements[$original] = $this->getTextimage()
              ->styleByName($image_style)
              ->node($node)
              ->process($text)
              ->$callback_method();
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
              $this->logger->warning($msg);
            }
          }
        }
        elseif ($field_info->getFieldStorageDefinition()->getTypeProvider() == 'image') {
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
                ->$callback_method();
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
              $this->logger->warning($msg);
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
      // @todo check Notice: Undefined index: value in Drupal\textimage\TextimageFactory->getTextFieldText() (line 601 of modules/textimage/src/TextimageFactory.php).
      // when empty
      $text[] = strip_tags($value['value']);
    }
    return $text;
  }

}
