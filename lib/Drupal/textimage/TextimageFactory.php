<?php

/**
 * @file

 * Contains \Drupal\textimage\TextimageFactory.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\Timer;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\Core\Utility\Token;
use Drupal\field\Field;

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
   * Constructs a new TextimageFactory object.
   *
   * @param \Drupal\Core\Lock\DatabaseLockBackend $lock_service
   *   the lock service
   * @param \Drupal\Core\Utility\Token $token_service
   *   the token resolution service
   */
  // @todo inject cache??
  public function __construct(DatabaseLockBackend $lock_service, Token $token_service) {
    $this->lock = $lock_service;
    $this->token = $token_service;
  }

  /**
   * Get a Textimage URL, building the image if necessary.
   *
   * @param string $style_name
   *   the image style name.
   * @param array $effects_outline
   *   an outline of the style's effects data.
   * @param array $text
   *   text to be used to deliver the image.
   * @param string $extension
   *   (optional) file extension to be delivered (png jpg jpeg gif). Defaults
   *   to 'png'.
   * @param bool $caching
   *   (optional) TRUE if image caching has to be used. Defaults to TRUE.
   * @param object $node
   *   (optional) a node entity. It is used for resolving the tokens in the
   *   text effects.
   * @param object $source_image_file
   *   (optional) a file entity. It is used for resolving the tokens
   *   in the text effects.
   * @param string $target_uri
   *   (optional) defines the URI where the Textimage image file should be
   *   saved. Disables caching.
   *
   * @return array
   *   the URL of the image, or NULL in case of failure
   */
  public function getImageUrl($style_name, $effects_outline, $text, $extension = 'png', $caching = TRUE, $node = NULL, $source_image_file = NULL, $target_uri = NULL) {
    $uri = $this->getImageUri($style_name, $effects_outline, $text, $extension, $caching, $node, $source_image_file, $target_uri);
    return $uri ? file_create_url($uri) : NULL;
  }

  /**
   * Get a Textimage URI, building the image if necessary.
   *
   * @param name $style_name
   *   the image style name.
   * @param array $effects_outline
   *   an outline of the style's effects data.
   * @param array $text
   *   text to be used to deliver the image.
   * @param string $extension
   *   (optional) file extension to be delivered (png jpg jpeg gif). Defaults
   *   to 'png'.
   * @param bool $caching
   *   (optional) TRUE if image caching has to be used. Defaults to TRUE.
   * @param object $node
   *   (optional) a node entity. It is used for resolving the tokens in the
   *   text effects.
   * @param object $source_image_file
   *   (optional) a file entity. It is used for resolving the tokens
   *   in the text effects.
   * @param string $target_uri
   *   (optional) defines the URI where the Textimage image file should be
   *   saved. Disables caching.
   *
   * @return array
   *   the URI of the image, or NULL in case of failure
   */
  public function getImageUri($style_name, $effects_outline, $text, $extension = 'png', $caching = TRUE, $node = NULL, $source_image_file = NULL, $target_uri = NULL) {

    if ($style_name) {
      // Retrieve Textimage style and process via processImageRequest.
      $textimage_style = entity_load('textimage_style', $style_name);

      // @todo temp hack to generate a fake entity if not existing
      if (!$textimage_style) {
        $textimage_style = entity_create('textimage_style', array('id' => $style_name));
      }

      if ($textimage_style) {
        return $this->processImageRequest($textimage_style, NULL, $text, $extension, $caching, $node, $source_image_file, $target_uri);
      }
      else {
        _textimage_diag(t("Textimage could not find image style '@style'.", array('@style' => $style_name)), WATCHDOG_ERROR);
        return NULL;
      }
    }
    elseif (!empty($effects_outline) && is_array($effects_outline)) {
      $textimage_style = entity_create('textimage_style', array());
      return $this->processImageRequest($textimage_style, $effects_outline, $text, $extension, $caching, $node, $source_image_file, $target_uri);
    }

    _textimage_diag(t("No input was specified while invoking Textimage."), WATCHDOG_ERROR);
    return NULL;
  }

  /**
   * Process image delivery request.
   *
   * @param @todo $textimage_style
   *   the image style
   * @param array $effects_outline
   *   an outline of the style's effects data
   * @param array $text
   *   text to be used to deliver the image
   * @param string $extension
   *   file extension to be delivered (png jpg jpeg gif)
   * @param bool $caching
   *   (optional) TRUE if image caching has to be used. Defaults to TRUE.
   * @param object $node
   *   (optional) a node entity. It is used for resolving the tokens in the
   *   text effects.
   * @param object $source_image_file
   *   (optional) a file entity. It is used for resolving the tokens
   *   in the text effects.
   * @param string $target_uri
   *   (optional) defines the URI where the Textimage image file should be
   *   saved. Disables caching.
   *
   * @return array
   *   the uri of the image, or NULL in case of failure
   */
  public function processImageRequest($textimage_style, $effects_outline, &$text, $extension, $caching = TRUE, $node = NULL, $source_image_file = NULL, $target_uri = NULL) {

    $source_image_uri = isset($source_image_file) ? $source_image_file->getFileUri() : NULL;

    // Normalise $text to an array.
    if (!$text) {
      $text = array();
    }
    if (!is_array($text)) {
      $text = array($text);
    }

    // Get the style's effects outline.
    // This will be already set if the function is invoked via
    // theme_textimage_direct_image().
    if (!$effects_outline) {
      $effects_outline = $textimage_style->getImageStyle()->getEffects()->getConfiguration();
    }

    // Build an array with default text from effects.
    $default_text = array();
    foreach ($effects_outline as &$effect) {
      if ($effect['id'] == 'textimage_text') {
        $default_text[] = $effect['data']['text_string'];
      }
    }

    // Process text to resolve tokens and required case conversions.
    $processed_text = array();
    foreach ($effects_outline as $e => $e_data) {
      if ($e_data['id'] == 'textimage_text') {
        $text_item = array_shift($text);
        $default_text_item = array_shift($default_text);
        if ($text_item) {
          // Replace any tokens in text with run-time values.
          $text_item = ($text_item == '[textimage:default]') ? $default_text_item : $text_item;
          $processed_text[] = $this->processTextString($text_item, $e_data['data']['text']['case_format'], $node, $source_image_file);
        }
        elseif ($default_text_item) {
          $processed_text[] = $this->processTextString($default_text_item, $e_data['data']['text']['case_format'], $node, $source_image_file);
        }
        else {
          $processed_text[] = t('* Missing text *');
        }
      }
    }
    $text = $processed_text;

    // Remove default text from effects outline, as actual runtime text goes
    // separately to the hash.
    foreach ($effects_outline as $k => $k_data) {
      if ($k_data['id'] == 'textimage_text') {
        unset($effects_outline[$k]['data']['text_string']);
      }
    }

    // Data for this textimage. Use a dummy filename at this stage,
    // purely to resolve the mime type.
    $image_data = array(
      'text'       => $processed_text,
      'filemime'   => file_get_mimetype('dummy.' . $extension),
      'extension'  => $extension,
      'source'     => $source_image_uri,
    );

    // Get md5 hash for cache checking.
    $hash_input = array(
      'effects_outline'     => $effects_outline,
      'image_data'          => $image_data,
    );
    $hash = md5(serialize($hash_input));

    // Disable caching if the target URI is set.
    if ($target_uri) {
      $caching = FALSE;
    }

    // Check cache and/or store and return if db and file hit.
    if ($caching) {
      if ($uri = $this->getCached($hash, $textimage_style->id())) {
        return $uri;
      }
    }

    // No cached images are available. Prepare to build one.
    Timer::start('TextimageStyle::processImageRequest');

    // Get URI of the to-be image file.
    if (!$target_uri) {
      $uri = $this->buildUri($hash, $textimage_style->id(), $processed_text, $extension, $caching);
    }
    else {
      $uri = $target_uri;
    }

    // Build the image.
    if (!$this->buildImage($textimage_style, $effects_outline, $processed_text, $source_image_uri, $uri, $extension)) {
      _textimage_diag(t("Textimage failed to build an image for image style '@style'.", array('@style' => $style_name)), WATCHDOG_ERROR, __FUNCTION__);
      return NULL;
    }

    // @todo - why is last text string not dropped???
    foreach ($effects_outline as $k => $k_data) {
      if ($k_data['id'] == 'textimage_text') {
        unset($effects_outline[$k]['data']['text_string']);
      }
    }

    // Saves db imagestore data.
    if ($caching) {
      $this->setCached($hash, $uri, $textimage_style->id());
      $this->putInStore($textimage_style->id(), $hash, $effects_outline, $image_data, $uri, Timer::read('TextimageStyle::processImageRequest'));
    }

    // Stop the image generation timer.
    Timer::stop('TextimageStyle::processImageRequest');

    return $uri;
  }

  /**
   * Process text string, detokenise and apply case conversion.
   */
  public function processTextString($text, $case_format, $node = NULL, $source_image_file = NULL) {

    // Replace any tokens in text with run-time values.
    global $user;
    $text = $this->token->replace(
      $text,
      array(
        'user' => $user,
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
        return preg_replace_callback('/(\w+)/', function ($m) { return Unicode::ucfirst($m[1]); }, $text);

      default:
        return $text;

    }
  }

  /**
   * Build the image via ImageStyle::createDerivative() method.
   *
   * @param @todo $textimage_style
   *   the image style
   * @param string $source
   *   uri of the source image
   * @param string $target
   *   uri of the derivative image to be built
   * @param string $extension
   *   file extension to be delivered (png jpg jpeg gif)
   *
   * @return bool
   *   TRUE if successful, FALSE in case of failure
   */
  protected function buildImage($textimage_style, $effects_outline, $processed_text, $source, $target, $extension) {

    // If no source image specified, we are processing a pure Textimage
    // request. In that case we need to use a dummy 1x1 image stored
    // in textimage/misc/images, and prepend an additional
    // 'textimage_background' effect to ensure we start with a clean
    // background.
    if (!$source) {
      $source = drupal_get_path('module', 'textimage') . '/misc/images/base.' . $extension;
      $cleanup_effect = array();
      $cleanup_effect['id'] = 'textimage_background';
      $cleanup_effect['weight'] = -90; // @todo better
      $cleanup_effect['data'] = array(
        'background_image' => array(
          'mode' => '',
        ),
      );
      $effects_outline[] = $cleanup_effect;
    }

    // Inject processed text in the textimage_text effects data.
    foreach ($effects_outline as $e => &$e_data) {
      if ($e_data['id'] == 'textimage_text') {
        $e_data['data']['text_string'] = array_shift($processed_text);
      }
    }

    // Build a runtime-only style, to reflect actual text values.
    $textimage_style->buildFromEffectsOutline($effects_outline);

    // Reset state.
    $this->setState();
    $this->setState('building_module', 'textimage');

    // Try a lock to the file generation process. If cannot get the lock,
    // return success if the file exists already. Otherwise return failure.
    $lock_name = 'textimage_process:' . $target;
    if(!$lock_acquired = $this->lock->acquire($lock_name)) {
      return file_exists($target) ? TRUE : FALSE;
    }

    // Generate the image.
    $success = $textimage_style->getImageStyle()->createDerivative($source, $target);

    // Release lock.
    if (!empty($lock_acquired)) {
      $this->lock->release($lock_name);
    }

    // Reset state.
    $this->setState();

    return $success;
  }

  /**
   * Determine image file name.
   *
   * If file name is human readable, then image would go to:
   *
   *   {style_wrapper}://textimage/{style}/{file name}.{extension}
   *
   * in all other cases, an appropriate directory structure is in place to
   * support styled, unstyled and uncached (temporary) image files:
   *
   * for images with a supporting image style (styled) -
   *   {textimage_store_wrapper}://textimage_store/styled_hashed/{style}/{file name}.{extension}
   *
   * for images generated via direct theme (unstyled) -
   *   {textimage_store_wrapper}://textimage_store/unstyled_hashed/{file name}.{extension}
   *
   * for uncached, temporary -
   *   {textimage_store_wrapper}://textimage_store/uncached/{file name}.{extension}
   *
   * @param string $hash
   *   md5 hash of the image to be built
   * @param string $style_name
   *   the image style name
   * @param array $text
   *   text to be used to deliver the image
   * @param string $extension
   *   file extension to be delivered (png jpg jpeg gif)
   * @param bool $caching
   *   TRUE if image caching has to be used
   *
   * @return string
   *   URI of the image, or NULL in case of failure
   */
  protected function buildUri($hash, $style_name, array $text, $extension, $caching) {

    // If style and caching are set, then try a clear file uri.
    if ($style_name and $caching) {
      if ($uri = $this->getStyledImageClearFileUri($style_name, $text, $extension)) {
        return $uri;
      }
    }

    // Otherwise, the hash will be the file name, and files stored in
    // textimage_store.
    if ($caching) {
      $base_name = $hash . '.' . $extension;
      if ($style_name) {
        $uri = _textimage_get_store_path('styled_hashed/') . $style_name . '/' . $base_name;
      }
      else {
        $uri = _textimage_get_store_path('unstyled_hashed/') . $base_name;
      }
    }
    else {
      $base_name = md5(session_id() . microtime()) . '.' . $extension;
      $uri = _textimage_get_store_path('uncached/') . $base_name;
    }

    return $uri;
  }

  /**
   * Return a human readable name for the image file, if possible.
   *
   * If a style-based image is requested, then hopefully a human readable
   * file name can be set.
   */
  protected function getStyledImageClearFileUri($style_name, array $text, $extension) {

    // Get a single string out of all the text.
    $file_name = implode('---', $text);

    // Filenames longer than 200 characters will fail in most filesystems.
    if (Unicode::strlen($file_name) > 200) {
      // Need to proceed with hash-based file names.
      _textimage_diag(
        t(
          "Textimage clear file name too long: @file_name...",
          array(
            '@file_name' => Unicode::substr($file_name, 0, 60),
          )
        ),
        WATCHDOG_DEBUG,
        __FUNCTION__
      );
      return NULL;
    }

    // Strip control characters (ASCII value < 32). Though these are allowed
    // in some filesystems, not many applications handle them well. Also, strip
    // slashes and backslashes that usually indicate directories.
    $base_name = preg_replace('/[\x00-\x1F]|\\/|\\\\/u', '_', $file_name);
    if (Unicode::substr(PHP_OS, 0, 3) == 'WIN') {
      // These characters are not allowed in Windows filenames.
      $base_name = str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $base_name);
    }
    if ($file_name <> $base_name) {
      // Need to proceed with hash-based file names.
      _textimage_diag(
        t(
          "Textimage clear file name contains unallowed characters: @file_name...",
          array(
            '@file_name' => Unicode::substr($file_name, 0, 60),
          )
        ),
        WATCHDOG_DEBUG,
        __FUNCTION__
      );
      return NULL;
    }

    $base_name = $file_name . '.' . $extension;
// @todo   $uri = $textimage_style['textimage']['uri_scheme'] . '://textimage/' . $style_name . '/' . $base_name;
    $uri = 'public' . '://textimage/' . $style_name . '/' . $base_name;
    return $uri;
  }

  /**
   * Get a cached image.
   *
   * Cache and store are checked for existing image files.
   *
   * @param string $hash
   *   md5 hash of the image data
   *
   * @return string
   *   URI if an existing image file can be used, NULL if no hit
   */
  protected function getCached($hash, $style_name) {

    // At first, check cache.
    if ($cached = cache('textimage')->get('tiid:' . $hash)) {
      if (is_file($cached->data['uri'])) {
        return $cached->data['uri'];
      }
    }

    // No cache. Check if we have the hash in store.
    $stored_image = db_select('textimage_store', 'ic')
        ->fields('ic')
        ->condition('tiid', $hash, '=')
        ->execute()
        ->fetchAssoc();

    // Not in stock, return to make.
    if (!$stored_image) {
      return NULL;
    }

    // In stock, check file is there.
    $uri = $stored_image['uri'];
    if (is_file($uri)) {
      $this->setCached($hash, $uri, $style_name);
      return $uri;
    }
    else {
      return NULL;
    }

  }

  /**
   * Cache image uri.
   *
   * @param string $hash
   *   md5 hash of the image data
   * @param string $uri
   *   uri of the image
   * @param string $style_name
   *   the image style name
   */
  protected function setCached($hash, $uri, $style_name = NULL) {
    $data = array(
      'uri' => $uri,
    );
    $tags = array('tiid' => TRUE);
    if (!empty($style_name)) {
      $tags['style'] = $style_name;
    }
    cache('textimage')->set('tiid:' . $hash, $data, Cache::PERMANENT, $tags);
  }

  /**
   * Store image details.
   *
   * @param string $style_name
   *   the image style name
   * @param string $hash
   *   md5 hash of the image data
   * @param array $effects_outline
   *   an outline of the style's effects data
   * @param array $image_data
   *   the image input data
   * @param string $uri
   *   uri of the image
   * @param int $timer
   *   image generation time in milliseconds
   */
  protected function putInStore($style_name, $hash, $effects_outline, $image_data, $uri, $timer) {
    $stored_image = array(
      'tiid' => $hash,
      'is_void' => 0,
      'style_name' => $style_name,
      'uri' => $uri,
      'effects_outline' => $effects_outline,
      'image_data' => $image_data,
      'timer' => $timer,
      'timestamp' => REQUEST_TIME,
    );
    try {
      $r = drupal_write_record('textimage_store', $stored_image);
    }
    catch(Exception $error) {
      $r = drupal_write_record('textimage_store', $stored_image, array('tiid'));
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

    // Determine the callback function
    switch ($key) {
      case 'uri':
        $callback_function = 'getImageUri';
        break;

      case 'url':
        $callback_function = 'getImageUrl';
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
      $field_info = Field::fieldInfo()->getField('node', $field_name);  // @todo inject Field???
      if (!$field_info) {
        continue;
      }

      // Get node (bundle) dependent field info, continue if missing.
      $node_type = $node->getType();
      $instance_info = Field::fieldInfo()->getInstance('node', $node_type, $field_name);
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
            $replacements[$original] = $this->$callback_function(
              $image_style,
              NULL,
              $text,
              'png',
              TRUE,
              $node
            );
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
              $ret[] = $this->$callback_function(
                $image_style,
                NULL,
                NULL,
                'png',
                TRUE,
                $node,
                $item->entity
              );
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

  /**
   * @todo not needed, use $textimage_style->getImageStyle()->getEffects()->getConfiguration()

   Build an outline of the style effects' data, given the style name.
   *
   * @param string $style_name
   *   Image style name.
   *
   * @return array
   *   a simple array, where each element is an associative array of
   *   'id' => the effect id
   *   'data' => the effect data
   */
/*  public static function getStyleEffectsOutline($style_name) {
    if ($style = static::get($style_name)) {
      return static::getEffectsOutline($style);
    }
    else {
      return NULL;
    }
  }*/

}
