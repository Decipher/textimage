<?php

/**
 * @file
 * Contains \Drupal\textimage\Textimage.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\Timer;
use Drupal\Component\Utility\Unicode;
use Drupal\image\ImageStyleInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

class Textimage {

  /**
   * The Textimage factory service.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $factory;

  /**
   * The textimage cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Textimage id.
   *
   * It is a SHA256 hash of the textimage effects and data.
   *
   * @var string
   *
   * @see \Drupal\textimage\Textimage::process()
   */
  protected $id = NULL;

  /**
   * If this Textimage has been processed.
   *
   * @var bool
   */
  protected $processed = FALSE;

  /**
   * Textimage metadata.
   *
   * @var array
   */
  protected $imageData = array();

  /**
   * Textimage execution time.
   *
   * @var int
   */
  protected $timer = NULL;

  /**
   * Textimage URI.
   *
   * @var string
   */
  protected $uri = NULL;

  /**
   * Image style used for this Textimage.
   *
   * @var \Drupal\image\ImageStyleInterface
   */
  protected $style = NULL;

  /**
   * The array of image effects for this Textimage.
   *
   * @var array
   */
  protected $effects = array();

  /**
   * The array of text elements for this Textimage.
   *
   * @var array
   */
  protected $text = array();

  /**
   * The file extension for this Textimage.
   *
   * @var string
   */
  protected $extension = 'png';

  /**
   * RGB hex color to be used for GIF images.
   *
   * @var string
   */
  protected $gifTransparentColor;

  /**
   * If this Textimage has to be cached.
   *
   * @var bool
   */
  protected $caching = TRUE;

  /**
   * A node entity to resolve node tokens.
   *
   * @var \Drupal\node\NodeInterface;
   */
  protected $node = NULL;

  /**
   * An image file entity.
   *
   * The source file used to build the image derivative in standard image
   * system context. Also used to track Textimages from image fields formatted
   * through Textimage field display formatter and to resolve file tokens.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $sourceImageFile = NULL;

  /**
   * If this Textimage has to use a hash filename instead of human readable.
   *
   * @var bool
   */
  protected $forceHashedFilename = FALSE;

  /**
   * If this Textimage has to be created at a specific URI.
   *
   * @var bool
   */
  protected $forcedUri = FALSE;

  /**
   * Constructs a Textimage object.
   *
   * @param \Drupal\textimage\TextimageFactory $textimage_factory
   *   The Textimage factory.
   */
  public function __construct(TextimageFactory $textimage_factory) {
    $this->factory = $textimage_factory;
  }

  /**
   * Set a property to a specified value.
   *
   * A Textimage already processed will not allow changes.
   *
   * @param string $property
   *   the property to set
   * @param mixed $value
   *   the value to set
   *
   * @return $this
   */
  protected function set($property, $value) {
    if (!property_exists($this, $property)) {
      throw new TextimageException(t("Attempted to set non existing property '@property'.", array('@property' => $property)));
    }
    if (!$this->processed) {
      $this->$property = $value;
    }
    else {
      throw new TextimageException(t("Attempted to set property '@property' when image was processed already.", array('@property' => $property)));
    }
    return $this;
  }

  /**
   * Set the image style.
   *
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   the image style to be used to derive the Textimage
   *
   * @return $this
   */
  public function style(ImageStyleInterface $image_style) {
    if ($this->factory->isTextimage($image_style)) {
      $this->set('style', $image_style);
      $effects = @$this->style->getEffects()->getConfiguration();
      $this->set('effects', $effects);
    }
    return $this;
  }

  /**
   * Set the image style, from the style name
   *
   * @param string $image_style_name
   *   the name of the image style to be used to derive the Textimage
   *
   * @return $this
   */
  public function styleByName($image_style_name) {
    if ($image_style_name) {
      // Retrieve Textimage style.
      if ($image_style = entity_load('image_style', $image_style_name)) {
        return $this->style($image_style);
      }
      else {
        $this->factory->getLogger()->error(t("Textimage could not find image style '@style'.", ['@style' => $image_style_name]));
      }
    }
    else {
      $this->factory->getLogger()->error(t("Image style not specified while processing a Textimage."));
    }
    return $this;
  }

  /**
   * Set the image effects.
   *
   * @param array $effects
   *   An array of image effects. Since Textimage manipulates effects before
   *   rendering the image, the style effects are copied here to allow that.
   *
   * @return $this
   */
  public function effects(array $effects) {
    return $this->set('effects', $effects);
  }

  /**
   * Set the image file extension.
   *
   * @param string $extension
   *   The file extension to be used (e.g. jpg/png/gif).
   *
   * @return $this
   */
  public function extension($extension) {
    return $this->set('extension', $extension);
  }

  /**
   * Set the RGB hex color to be used for GIF images.
   *
   * @param string $color
   *   The color to be used for transparent.
   *
   * @return $this
   */
  public function gifTransparentColor($color) {
    return $this->set('gifTransparentColor', $color);
  }

  /**
   * Set the image source file.
   *
   * @param \Drupal\file\FileInterface $source_image_file
   *   A file entity.
   *
   * @return $this
   */
  public function sourceImageFile(FileInterface $source_image_file) {
    return $this->set('sourceImageFile', $source_image_file);
  }

  /**
   * Set a node entity to resolve node tokens.
   *
   * @param \Drupal\node\NodeInterface $snode
   *   A node entity.
   *
   * @return $this
   */
  public function node(NodeInterface $node) {
    return $this->set('node', $node);
  }

  /**
   * Set caching.
   *
   * @param bool $caching
   *   TRUE if caching is required for this Textimage.
   *
   * @return $this
   */
  public function setCaching($caching) {
    // If destination URI has been forced, this setting is not effective.
    if (!$this->forcedUri) {
      $this->set('caching', $caching);
    }
    return $this;
  }

  /**
   * Set image destination URI.
   *
   * @param string $uri
   *   A valid URI.
   *
   * @return $this
   */
  public function setTargetUri($uri) {
    if ($uri) {
      $this->set('uri', $uri);
      $this->set('caching', FALSE);
      $this->set('forcedUri', TRUE);
    }
    return $this;
  }

  /**
   * Force hashed filename.
   *
   * @param bool $force_hashed_filename
   *   TRUE if Textimage has to use an hashed filename even if a human
   *   readable one could be attempted.
   *
   * @return $this
   */
  public function setHashedFilename($force_hashed_filename) {
    return $this->set('forceHashedFilename', $force_hashed_filename);
  }

  /**
   * Return the Textimage id.
   *
   * @return string
   *   A SHA256 hash.
   */
  public function id() {
    return $this->processed ? $this->id : NULL;
  }

  /**
   * Return the processed text.
   *
   * @return array
   *   An array of fully processed text elements.
   */
  public function getText() {
    return $this->processed ? $this->text : array();
  }

  /**
   * Return the URI of the Textimage.
   *
   * @return string
   *   An URI.
   */
  public function getUri() {
    return $this->processed ? $this->uri : NULL;
  }

  /**
   * Return the URL of the Textimage.
   *
   * @return string
   *   An URL.
   */
  public function getUrl() {
    return $this->processed ? ($this->uri ? file_create_url($this->uri) : NULL) : NULL;
  }

  /**
   * Load Textimage metadata from store.
   *
   * If the image file is missing at URI, it is rebuilt.
   *
   * @param string $id
   *   The id of the Textimage to load.
   *
   * @return $this
   */
  public function load($id) {

    // Do not re-process.
    if ($this->processed) {
      return $this;
    }

    // Check if we have the hash in store.
    $stored_image = $this->factory->getDatabase()->select('textimage_store', 'ic')
        ->fields('ic')
        ->condition('tiid', $id, '=')
        ->execute()
        ->fetchAssoc();

    // Not in stock, return.
    if (!$stored_image) {
      return $this;
    }

    // Restore properties.
    $this->id = $stored_image['tiid'];
    $is_void = $stored_image['is_void'];
    $this->styleByName($stored_image['style_name']);
    if ($is_void) {
      $this->effects = unserialize($stored_image['effects_outline']);
    }
    $this->imageData = unserialize($stored_image['image_data']);
    $this->text = $this->imageData['text'];
    $this->extension = $this->imageData['extension'];
    $this->forceHashedFilename = $this->imageData['forceHashedFilename'];
    $this->timer = $stored_image['timer'];

    // In stock, check file is there.
    if (is_file($stored_image['uri'])) {
      $this->uri = $stored_image['uri'];
      $this->processed = TRUE;
    }
    else {
      // If not, rebuild image file.
      $this->buildImage();
    }

    return $this;
  }

  /**
   * Process the Textimage, with the required raw text.
   *
   * @param array $text
   *   An array of text strings, with tokens not resolved.
   *
   * @return $this
   */
  public function process($text) {

    // Do not re-process.
    if ($this->processed) {
      return $this;
    }

    // Effects must be loaded.
    if(empty($this->effects)) {
      $this->factory->getLogger()->error(t("Textimage had no image effects to process."));
      return $this;
    }

    // Normalise $text to an array.
    if (!$text) {
      $text = array();
    }
    if (!is_array($text)) {
      $text = array($text);
    }

    // Build an array with default text from effects.
    $default_text = array();
    foreach ($this->effects as &$effect) {
      if ($effect['id'] == 'textimage_text') {
        $default_text[] = $effect['data']['text_string'];
      }
    }

    // Process text to resolve tokens and required case conversions.
    $processed_text = array();
    foreach ($this->effects as $e => $e_data) {
      if ($e_data['id'] == 'textimage_text') {
        $text_item = array_shift($text);
        $default_text_item = array_shift($default_text);
        if ($text_item) {
          // Replace any tokens in text with run-time values.
          $text_item = ($text_item == '[textimage:default]') ? $default_text_item : $text_item;
          $processed_text[] = $this->factory->processTextString($text_item, $e_data['data']['text']['case_format'], $this->node, $this->sourceImageFile);
        }
        elseif ($default_text_item) {
          $processed_text[] = $this->factory->processTextString($default_text_item, $e_data['data']['text']['case_format'], $this->node, $this->sourceImageFile);
        }
        else {
          $processed_text[] = t('* Missing text *');
        }
      }
    }
    $this->text = $processed_text;
    if(empty($this->text)) {
      $this->factory->getLogger()->error(t("Textimage had no text to process."));
      return $this;
    }

    // Remove default text from effects outline, as actual runtime text goes
    // separately to the hash.
    foreach ($this->effects as &$effect) {
      if ($effect['id'] == 'textimage_text') {
        unset($effect['data']['text_string']);
      }
    }

    // Data for this textimage.
    $this->imageData = array(
      'text'                => $this->text,
      'extension'           => $this->extension,
      'sourceImage'         => $this->sourceImageFile ? $this->sourceImageFile->getFileUri() : NULL,
      'forceHashedFilename' => $this->forceHashedFilename,
    );

    // Get SHA256 hash, being the Textimage id, for cache checking.
    $hash_input = array(
      'effects_outline'     => $this->effects,
      'image_data'          => $this->imageData,
    );
    $this->id = hash('sha256', serialize($hash_input));

    // Check cache and/or store and return if db and file hit.
    if ($this->caching && $this->getCached()) {
      $this->processed = TRUE;
    }
    else {
      // If not found, build the image.
      $this->buildImage();
    }

    return $this;
  }

  /**
   * Build the image via core ImageStyle::createDerivative() method.
   *
   * @return $this
   */
  protected function buildImage() {

    // Track the image generation time.
    Timer::start('Textimage::process');

    // Get URI of the to-be image file.
    if (!$this->uri) {
      $this->buildUri();
    }

    // Inject processed text in the textimage_text effects data.
    $runtime_effects = [];
    $i = 0;
    foreach ($this->effects as $effect => $data) {
      $runtime_effects[$effect] = $data;
      if ($data['id'] == 'textimage_text' && isset($this->text[$i])) {
        $runtime_effects[$effect]['data']['text_string'] = $this->text[$i];
        $i++;
      }
    }

    // If no source image specified, we are processing a pure Textimage
    // request. In that case we create a new 1x1 image to ensure we start
    // with a clean background.
    $source = isset($this->sourceImageFile) ? $this->sourceImageFile->getFileUri() : NULL;
    $image = $this->factory->getImageFactory()->get($source);
    if (!$source) {
      $image->createNew(1, 1, $this->extension, $this->gifTransparentColor);
    }

    // Build a runtime-only style.
    $runtime_style = $this->factory->buildStyleFromEffects($runtime_effects);

    // Reset state.
    $this->factory->setState();
    $this->factory->setState('building_module', 'textimage');

    // Try a lock to the file generation process. If cannot get the lock,
    // return success if the file exists already. Otherwise return failure.
    $lock_name = 'textimage_process:' . $this->uri;
    if(!$lock_acquired = $this->factory->getLock()->acquire($lock_name)) {
      return file_exists($this->uri) ? TRUE : FALSE;
    }

    // Generate the image.
    if (!$this->processed = $this->createDerivativeFromImage($runtime_style, $image, $this->uri)) {
      if (isset($this->style)) {
        $this->factory->getLogger()->error(t("Textimage failed to build an image for image style '@style'.", ['@style' => $this->style->id()]));
      }
      else {
        $this->factory->getLogger()->error(t("Textimage failed to build an image."));
      }
    }
    $this->factory->getLogger()->debug(t("Built Textimage, @uri", ['@uri' => $this->uri]));

    // Release lock.
    if (!empty($lock_acquired)) {
      $this->factory->getLock()->release($lock_name);
    }

    // Reset state.
    $this->factory->setState();

    // Saves db imagestore data.
    if ($this->processed && $this->caching) {
      $this->setCached();
      $this->timer = Timer::read('Textimage::process');
      $this->putInStore();
    }

    // Stop the image generation timer.
    Timer::stop('Textimage::process');

  }

  /**
   * Create the derivative image from the Image object.
   *
   * @todo (core) remove if #2359443 gets in
   */
  protected function createDerivativeFromImage($style, $image, $derivative_uri) {
    // Get the folder for the final location of this style.
    $directory = drupal_dirname($derivative_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $this->factory->getLogger()->error('Failed to create Textimage directory: %directory', array('%directory' => $directory));
      return FALSE;
    }

    if (!$image->isValid()) {
      return FALSE;
    }

    foreach ($style->getEffects() as $effect) {
      $effect->applyEffect($image);
    }

    if (!$image->save($derivative_uri)) {
      if (file_exists($derivative_uri)) {
        $this->factory->getLogger()->error('Cached image file %destination already exists. There may be an issue with your rewrite configuration.', array('%destination' => $derivative_uri));
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Set URI to image file.
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
   */
  protected function buildUri() {

    // If style and caching are set, then try a clear file uri.
    if ($this->style && $this->caching && !$this->forceHashedFilename && $this->getStyledImageClearFileUri()) {
      return;
    }

    // Otherwise, the hash will be the file name, and files stored in
    // textimage_store.
    if ($this->caching) {
      $base_name = $this->id . '.' . $this->extension;
      if ($this->style) {
        $this->uri = $this->factory->getStorePath('styled_hashed/') . $this->style->id() . '/' . $base_name;
      }
      else {
        $this->uri = $this->factory->getStorePath('unstyled_hashed/') . $base_name;
      }
    }
    else {
      $base_name = hash('sha256', session_id() . microtime()) . '.' . $this->extension;
      $this->uri = $this->factory->getStorePath('uncached/') . $base_name;
    }

  }

  /**
   * Set URI to a human readable name for the image file, if possible.
   *
   * If a style-based image is requested, then hopefully a human readable
   * file name can be set.
   *
   * @return bool
   *   TRUE if URI is set to human readable file name
   */
  protected function getStyledImageClearFileUri() {

    // Get a single string out of all the text.
    $file_name = implode($this->factory->getConfig()->get('url_generation.text_separator'), $this->text);

    // Filenames longer than 200 characters will fail in most filesystems.
    if (Unicode::strlen($file_name) > 200) {
      // Need to proceed with hash-based file names.
      $this->factory->getLogger()->debug(t("Textimage clear file name too long: @file_name...", ['@file_name' => Unicode::substr($file_name, 0, 60)]));
      return FALSE;
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
      $this->factory->getLogger()->debug(t("Textimage clear file name contains unallowed characters: @file_name...", ['@file_name' => Unicode::substr($file_name, 0, 60)]));
      return FALSE;
    }

    $scheme = $this->style->getThirdPartySetting('textimage', 'uri_scheme', 'public');
    $base_name = $file_name . '.' . $this->extension;
    $this->uri = $scheme . '://textimage/' . $this->style->id() . '/' . $base_name;
    return TRUE;
  }

  /**
   * Get a cached Textimage.
   *
   * Cache and store are checked for existing image files.
   *
   * @return bool
   *   TRUE if an existing image file can be used, FALSE if no hit
   */
  protected function getCached() {

    // At first, check cache.
    if ($cached = $this->factory->getCache()->get('tiid:' . $this->id)) {
      if (is_file($cached->data['uri'])) {
        $this->uri = $cached->data['uri'];
        $this->factory->getLogger()->debug(t("Got Textimage from cache, @uri", array('@uri' => $this->uri)));
        return TRUE;
      }
    }

    // No cache. Check if we have the hash in store.
    $stored_image = $this->factory->getDatabase()->select('textimage_store', 'ic')
        ->fields('ic')
        ->condition('tiid', $this->id, '=')
        ->execute()
        ->fetchAssoc();

    // Not in stock, return to make.
    if (!$stored_image) {
      return FALSE;
    }

    // In stock, check file is there.
    $uri = $stored_image['uri'];
    if (is_file($uri)) {
      $this->uri = $uri;
      $this->factory->getLogger()->debug(t("Got Textimage from store, @uri", array('@uri' => $this->uri)));
      $this->setCached();
      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  /**
   * Cache image uri.
   *
   * @return $this
   */
  protected function setCached() {
    $tags = ['textimage_tiid'];
    if (isset($this->style) && $this->style->id()) {
      $tags[] = 'textimage_style:' . $this->style->id();
    }
    $this->factory->getCache()->set('tiid:' . $this->id, ['uri' => $this->uri], time() + (60 * 60 * 24), $tags);
    return $this;
  }

  /**
   * Store image details.
   */
  protected function putInStore() {
    $stored_image = array(
      'tiid' => $this->id,
      'is_void' => 0,
      'style_name' =>  $this->style ? $this->style->id() : NULL,
      'uri' =>  $this->uri,
      'effects_outline' => serialize($this->effects),
      'image_data' => serialize($this->imageData),
      'timer' => $this->timer,
      'timestamp' => REQUEST_TIME,
    );
    $this->factory->getDatabase()->merge('textimage_store')
      ->key(array('tiid' => $this->id))
      ->fields($stored_image)
      ->execute();
  }

}
