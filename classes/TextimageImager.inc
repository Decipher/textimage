<?php
/**
 * @file
 * Textimage - Image processor class.
 */

/**
 * Textimage - Image processor class.
 */
abstract class TextimageImager {

  /**
   * Get a Textimage URL, building the image if necessary.
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
   *   the URL of the image, or NULL in case of failure
   */
  public static function getImageUrl($style_name, $effects_outline, $text, $extension = 'png', $caching = TRUE, $node = NULL, $source_image_file = NULL, $target_uri = NULL) {
    $uri = self::getImageUri($style_name, $effects_outline, $text, $extension, $caching, $node, $source_image_file, $target_uri);
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
  public static function getImageUri($style_name, $effects_outline, $text, $extension = 'png', $caching = TRUE, $node = NULL, $source_image_file = NULL, $target_uri = NULL) {

    if ($style_name) {
      // Retrieve Textimage style and process via processImageRequest.
      $style = TextimageStyles::get($style_name);
      if ($style) {
        return self::processImageRequest($style, NULL, $text, $extension, $caching, $node, $source_image_file, $target_uri);
      }
      else {
        _textimage_diag(t("Textimage could not find image style '@style'.", array('@style' => $style_name)), WATCHDOG_ERROR);
        return NULL;
      }
    }
    elseif (!empty($effects_outline) && is_array($effects_outline)) {
      return self::processImageRequest(NULL, $effects_outline, $text, $extension, $caching, $node, $source_image_file, $target_uri);
    }

    _textimage_diag(t("No input was specified while invoking Textimage."), WATCHDOG_ERROR);
    return NULL;
  }

  /**
   * Process image delivery request.
   *
   * @param array $style
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
  public static function processImageRequest($style, $effects_outline, &$text, $extension, $caching = TRUE, $node = NULL, $source_image_file = NULL, $target_uri = NULL) {

    $source_image_uri = isset($source_image_file) ? $source_image_file->uri : NULL;

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
      $effects_outline = TextimageStyles::getEffectsOutline($style);
    }

    // Build an array with default text from effects.
    $default_text = array();
    foreach ($effects_outline as &$effect) {
      if ($effect['name'] == 'textimage_text') {
        $default_text[] = $effect['data']['text_string'];
      }
    }

    // Process text to resolve tokens and required case conversions.
    $processed_text = array();
    foreach ($effects_outline as $e => $e_data) {
      if ($e_data['name'] == 'textimage_text') {
        $text_item = array_shift($text);
        $default_text_item = array_shift($default_text);
        if ($text_item) {
          // Replace any tokens in text with run-time values.
          $text_item = ($text_item == '[textimage:default]') ? $default_text_item : $text_item;
          $processed_text[] = self::processTextString($text_item, $e_data['data']['text']['case_format'], $node, $source_image_file);
        }
        elseif ($default_text_item) {
          $processed_text[] = self::processTextString($default_text_item, $e_data['data']['text']['case_format'], $node, $source_image_file);
        }
        else {
          $processed_text[] = t('* Missing text *');
        }
      }
    }
    $text = $processed_text;

    // Remove default text from effects outline, as actual runtime text goes
    // separately to the hash.
    foreach ($effects_outline as &$effect) {
      if ($effect['name'] == 'textimage_text') {
        unset($effect['data']['text_string']);
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
      if ($uri = self::getCached($hash)) {
        return $uri;
      }
    }

    // No cached images are available. Prepare to build one.
    timer_start('TextimageImager::build');

    // Get URI of the to-be image file.
    if (!$target_uri) {
      $uri = self::buildUri($hash, $style, $processed_text, $extension, $caching);
    }
    else {
      $uri = $target_uri;
    }

    // Build a runtime-only style, in case the image is being generated through
    // theme_textimage_direct_image().
    if (!$style) {
      $style = TextimageStyles::buildFromEffectsOutline($effects_outline);
    }

    // Inject processed text in the textimage_text effects data.
    foreach ($style['effects'] as $e => &$e_data) {
      if ($e_data['name'] == 'textimage_text') {
        $e_data['data']['text_string'] = array_shift($processed_text);
      }
    }

    // Build the image.
    if (!self::buildImage($style, $source_image_uri, $uri, $extension)) {
      _textimage_diag(t("Textimage failed to build an image for image style '@style'.", array('@style' => $style['name'])), WATCHDOG_ERROR, __FUNCTION__);
      return NULL;
    }

    // Track the image generation time.
    $timer = timer_stop('TextimageImager::build');

    // Saves db imagestore data.
    if ($caching) {
      self::setCached($hash, $uri);
      self::putInStore($style, $hash, $effects_outline, $image_data, $uri, $timer['time']);
    }

    return $uri;
  }

  /**
   * Process text string, detokenise and apply case conversion.
   */
  public static function processTextString($text, $case_format, $node = NULL, $source_image_file = NULL) {

    // Replace any tokens in text with run-time values.
    global $user;
    $text = token_replace(
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
        return drupal_strtoupper($text);

      case 'lower':
        return drupal_strtolower($text);

      case 'ucfirst':
        return drupal_ucfirst($text);

      case 'ucwords':
        return preg_replace_callback('/(\w+)/', array('TextimageImager', 'uppercaseWords'), $text);

      default:
        return $text;

    }
  }

  /**
   * Callback for capitalizing the first letter of each word in a string.
   */
  protected static function uppercaseWords($matches) {
    return drupal_ucfirst($matches[1]);
  }

  /**
   * Build the image via core image_style_create_derivative() function.
   *
   * @param array $style
   *   the image style
   * @param string $source
   *   uri of the source image
   * @param string $uri
   *   uri of the derivative image to be built
   * @param string $extension
   *   file extension to be delivered (png jpg jpeg gif)
   *
   * @return bool
   *   TRUE if successful, FALSE in case of failure
   */
  protected static function buildImage($style, $source, $uri, $extension) {

    // If no source image specified, we are processing a pure Textimage
    // request. In that case we need to use a dummy 1x1 image stored
    // in textimage/misc/images, and prepend an additional
    // 'textimage_background' effect to ensure we start with a clean
    // background.
    if (!$source) {
      $source = drupal_get_path('module', 'textimage') . '/misc/images/base.' . $extension;
      $cleanup_effect_id = max(array_keys($style['effects'])) + 1;
      $cleanup_effect = array(
        $cleanup_effect_id => image_effect_definition_load('textimage_background'),
      );
      $cleanup_effect[$cleanup_effect_id]['data'] = array(
        'background_image' => array(
          'mode' => '',
        ),
      );
      $style['effects'] = $cleanup_effect + $style['effects'];
    }

    // Reset state.
    self::setState();
    self::setState('building_module', 'textimage');

    // Try a lock to the file generation process. If cannot get the lock,
    // return success if the file exists already. Otherwise return failure.
    $lock_name = 'textimage_process:' . $uri;
    if(!$lock_acquired = lock_acquire($lock_name)) {
      return file_exists($uri) ? TRUE : FALSE;
    }

    // Generate the image.
    $success = image_style_create_derivative($style, $source, $uri);

    // Release lock.
    if (!empty($lock_acquired)) {
      lock_release($lock_name);
    }

    // Reset state.
    self::setState();

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
   * @param array $style
   *   the image style
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
  protected static function buildUri($hash, $style, array $text, $extension, $caching) {

    // If style and caching are set, then try a clear file uri.
    if ($style and $caching) {
      if ($uri = self::getStyledImageClearFileUri($style, $text, $extension)) {
        return $uri;
      }
    }

    // Otherwise, the hash will be the file name, and files stored in
    // textimage_store.
    if ($caching) {
      $base_name = $hash . '.' . $extension;
      if ($style) {
        $uri = _textimage_get_store_path('styled_hashed/') . $style['name'] . '/' . $base_name;
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
  protected static function getStyledImageClearFileUri($style, array $text, $extension) {

    // Get a single string out of all the text.
    $file_name = implode('-+-', $text);

    // Filenames longer than 200 characters will fail in most filesystems.
    if (drupal_strlen($file_name) > 200) {
      // Need to proceed with hash-based file names.
      _textimage_diag(
        t(
          "Textimage clear file name too long: @file_name...",
          array(
            '@file_name' => drupal_substr($file_name, 0, 60),
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
    if (drupal_substr(PHP_OS, 0, 3) == 'WIN') {
      // These characters are not allowed in Windows filenames.
      $base_name = str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $base_name);
    }
    if ($file_name <> $base_name) {
      // Need to proceed with hash-based file names.
      _textimage_diag(
        t(
          "Textimage clear file name contains unallowed characters: @file_name...",
          array(
            '@file_name' => drupal_substr($file_name, 0, 60),
          )
        ),
        WATCHDOG_DEBUG,
        __FUNCTION__
      );
      return NULL;
    }

    $base_name = $file_name . '.' . $extension;
    $uri = $style['textimage']['uri_scheme'] . '://textimage/' . $style['name'] . '/' . $base_name;
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
  protected static function getCached($hash) {

    // At first, check cache.
    if ($cached = cache_get('tiid:' . $hash, 'cache_textimage')) {
      if (is_file($cached->data['uri'])) {
        $uri = $cached->data['uri'];
        return $uri;
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
      self::setCached($hash, $uri);
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
   */
  protected static function setCached($hash, $uri) {
    $data = array(
      'uri' => $uri,
    );
    cache_set('tiid:' . $hash, $data, 'cache_textimage');
  }

  /**
   * Store image details.
   *
   * @param array $style
   *   the image style
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
  protected static function putInStore($style, $hash, $effects_outline, $image_data, $uri, $timer) {
    $stored_image = array(
      'tiid' => $hash,
      'is_void' => 0,
      'style_name' => $style ? (isset($style['name']) ? $style['name'] : NULL) : NULL,
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
  public static function getState($variable = NULL) {
    if ($variable) {
      return self::setState($variable);
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
  public static function setState($variable = NULL, $value = NULL) {
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

}
