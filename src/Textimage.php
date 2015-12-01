<?php

/**
 * @file
 * Contains \Drupal\textimage\Textimage.
 */

namespace Drupal\textimage;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\image\ImageEffectManager;
use Drupal\image\ImageStyleInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

class Textimage implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The Textimage factory service.
   *
   * @var \Drupal\textimage\TextimageFactory
   */
  protected $factory;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The textimage cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The Textimage logger.
   *
   * @var \Psr\Log\LoggerInterface.
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The image effect manager service.
   *
   * @var \Drupal\image\ImageEffectManager
   */
  protected $imageEffectManager;

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
   * If data for this Textimage has been processed.
   *
   * @var bool
   */
  protected $processed = FALSE;

  /**
   * If this Textimage has been built.
   *
   * @var bool
   */
  protected $built = FALSE;

  /**
   * Textimage metadata.
   *
   * @var array
   */
  protected $imageData = array();

  /**
   * Textimage URI.
   *
   * @var string
   */
  protected $uri = NULL;

  /**
   * Textimage width.
   *
   * @var int
   */
  protected $width = NULL;

  /**
   * Textimage height.
   *
   * @var int
   */
  protected $height = NULL;

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
  protected $extension = NULL;

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
   * An user entity to resolve user tokens.
   *
   * @var \Drupal\user\UserInterface;
   */
  protected $user = NULL;

  /**
   * If this Textimage has to be created at a specific URI.
   *
   * @var bool
   */
  protected $forcedUri = FALSE;

  /**
   * Bubbleable metadata of the Textimage.
   *
   * @var \Drupal\Core\Render\BubbleableMetadata
   */
  protected $bubbleableMetadata = NULL;

  /**
   * Constructs a Textimage object.
   *
   * @param \Drupal\textimage\TextimageFactory $textimage_factory
   *   The Textimage factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_service
   *   The lock service.
   * @param \Drupal\Core\Image\ImageFactory $image_factory
   *   The image factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Textimage logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_service
   *   The Textimage cache service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\image\ImageEffectManager $image_effect_manager
   *   The image effect manager service.
   */
  public function __construct(TextimageFactory $textimage_factory, LockBackendInterface $lock_service, ImageFactory $image_factory, ConfigFactoryInterface $config_factory, LoggerInterface $logger, CacheBackendInterface $cache_service, FileSystemInterface $file_system, ImageEffectManager $image_effect_manager) {
    $this->factory = $textimage_factory;
    $this->lock = $lock_service;
    $this->imageFactory = $image_factory;
    $this->config = $config_factory->get('textimage.settings');
    $this->logger = $logger;
    $this->cache = $cache_service;
    $this->fileSystem = $file_system;
    $this->imageEffectManager = $image_effect_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('textimage.factory'),
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('config.factory'),
      $container->get('textimage.logger'),
      $container->get('cache.textimage'),
      $container->get('file_system'),
      $container->get('plugin.manager.image.effect')
    );
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
      throw new TextimageException("Attempted to set non existing property '{$property}'");
    }
    if (!$this->processed) {
      $this->$property = $value;
    }
    else {
      throw new TextimageException("Attempted to set property '{$property}' when image was processed already");
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
    // Retrieve Textimage style.
    if ($image_style = ImageStyle::load($image_style_name)) {
      return $this->style($image_style);
    }
    else {
      $this->logger->error('Textimage could not find image style \'@style\'.', ['@style' => $image_style_name]);
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
   * Forces the image file extension.
   *
   * @param string $extension
   *   The file extension to be used (e.g. jpeg/png/gif).
   *
   * @return $this
   */
  public function forceExtension($extension) {
    if (!in_array($extension, $this->imageFactory->getSupportedExtensions())) {
      throw new TextimageException("Attempted to set an unsupported file image extension ({$extension})");
    }
    if ($this->extension) {
      throw new TextimageException("Extension already set");
    }
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
   * Sets the image source file.
   *
   * @param \Drupal\file\FileInterface $source_image_file
   *   A file entity.
   *
   * @return $this
   */
  public function sourceImageFile(FileInterface $source_image_file) {
    if ($source_image_file) {
      $this->set('sourceImageFile', $source_image_file);
    }
    return $this;
  }

  /**
   * Sets a node entity to resolve node tokens.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return $this
   */
  public function node(NodeInterface $node = NULL) {
    return $this->set('node', $node);
  }

  /**
   * Sets an user entity to resolve user tokens.
   *
   * @param \Drupal\user\UserInterface $user
   *   An user entity.
   *
   * @return $this
   */
  public function user(UserInterface $user = NULL) {
    return $this->set('user', $user);
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
    if ($this->uri) {
      throw new TextimageException("URI already set");
    }
    // @todo should force the extension
    if ($uri) {
      if (!file_valid_uri($uri)) {
        throw new TextimageException("Invalid target URI '{$uri}' specified");
      }
      $dir_name = $this->fileSystem->dirname($uri);
      $base_name = $this->fileSystem->basename($uri);
      $valid_uri = $this->createFilename($base_name, $dir_name);
      if ($uri != $valid_uri) {
        throw new TextimageException("Invalid target URI '{$uri}' specified");
      }
      $this->set('uri', $uri);
      $this->set('caching', FALSE);
      $this->set('forcedUri', TRUE);
    }
    return $this;
  }

  /**
   * Creates a full file path from a directory and filename.
   *
   * Copied parts of file_create_filename() to avoid file existence check.
   *
   * @param string $basename
   *   String filename
   * @param string $directory
   *   String containing the directory or parent URI.
   *
   * @return string
   *   File path consisting of $directory and a unique filename based off
   *   of $basename.
   */
  protected function createFilename($basename, $directory) {
    // Strip control characters (ASCII value < 32). Though these are allowed in
    // some filesystems, not many applications handle them well.
    $basename = preg_replace('/[\x00-\x1F]/u', '_', $basename);
    if (substr(PHP_OS, 0, 3) == 'WIN') {
      // These characters are not allowed in Windows filenames
      $basename = str_replace(array(':', '*', '?', '"', '<', '>', '|'), '_', $basename);
    }

    // A URI or path may already have a trailing slash or look like "public://".
    if (substr($directory, -1) == '/') {
      $separator = '';
    }
    else {
      $separator = '/';
    }

    return $directory . $separator . $basename;
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
    return $this->processed ? array_values($this->text) : [];
  }

  /**
   * Returns the URI of the Textimage.
   *
   * @return string
   *   An URI.
   */
  public function getUri() {
    return $this->processed ? $this->uri : NULL;
  }

  /**
   * Returns the URL of the Textimage.
   *
   * @return string
   *   An URL.
   */
  public function getUrl() {
    return $this->processed ? ($this->uri ? file_create_url($this->uri) : NULL) : NULL;
  }

  /**
   * Returns the height of the Textimage.
   *
   * @return int|null
   *   The height of the Textimage, or NULL if not available.
   */
  public function getHeight() {
    return $this->height;
  }

  /**
   * Returns the width of the Textimage.
   *
   * @return int|null
   *   The width of the Textimage, or NULL if not available.
   */
  public function getWidth() {
    return $this->width;
  }

  /**
   * Gets the bubbleable metadata of the Textimage.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata
   *   A BubbleableMetadata object.
   */
  public function getBubbleableMetadata() {
    return $this->processed ? $this->bubbleableMetadata : NULL;
  }

  /**
   * Sets the bubbleable metadata.
   *
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   A BubbleableMetadata object.
   *
   * @return $this
   */
  public function setBubbleableMetadata(BubbleableMetadata $bubbleable_metadata) {
    return $this->set('bubbleableMetadata', $bubbleable_metadata);
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
    $style = ImageStyle::create(array());
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
   * Load Textimage metadata from cache.
   *
   * @param string $id
   *   The id of the Textimage to load.
   *
   * @return bool
   *   TRUE if cache entry exists, FALSE otherwise.
   */
  public function load($id) {
    // Do not re-process.
    if ($this->processed) {
      return $this;
    }
    // Load from the cache.
    $this->id = $id;
    if ($cached_data = $this->getCachedData()) {
      $this->restoreFromCache($cached_data);
    }
    else {
      throw new TextimageException("Missing Textimage cache entry {$this->id}");
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
      $this->logger->error('Textimage had no image effects to process.');
      return $this;
    }

    // Collect bubbleable metadata.
    if (!$this->bubbleableMetadata) {
      $this->bubbleableMetadata = new BubbleableMetadata();
    }
    if ($this->style) {
      $this->bubbleableMetadata = $this->bubbleableMetadata->addCacheableDependency($this->style);
    }
    if ($this->sourceImageFile) {
      $this->bubbleableMetadata = $this->bubbleableMetadata->addCacheableDependency($this->sourceImageFile);
    }

    // Normalise $text to an array.
    if (!$text) {
      $text = array();
    }
    if (!is_array($text)) {
      $text = array($text);
    }

    // Find the default text from effects.
    $default_text = [];
    foreach ($this->effects as $uuid => $effect_configuration) {
      if ($effect_configuration['id'] == 'textimage_text') {
        $uuid = isset($effect_configuration['uuid']) ? $effect_configuration['uuid'] : $uuid;
        $default_text[$uuid] = $effect_configuration['data']['text_string'];
      }
    }

    // Process text to resolve tokens and required case conversions.
    $processed_text = [];
    $token_data = [
      'node' => $this->node,
      'file' => $this->sourceImageFile,
      'user' => $this->user,
    ];
    foreach ($default_text as $uuid => $default_text_item) {
      $text_item = array_shift($text);
      if ($text_item) {
        // Replace any tokens in text with run-time values.
        $text_item = ($text_item == '[textimage:default]') ? $default_text_item : $text_item;
        $processed_text[$uuid] = $this->factory->processTextString($text_item, $this->effects[$uuid]['data']['text']['case_format'], $token_data, $this->bubbleableMetadata);
      }
      else {
        $processed_text[$uuid] = $this->factory->processTextString($default_text_item, $this->effects[$uuid]['data']['text']['case_format'], $token_data, $this->bubbleableMetadata);
      }
    }
    $this->text = $processed_text;
    if(empty($this->text)) {
      $this->logger->error('Textimage had no text to process.');
      return $this;
    }

    // Set the output image file extension, and find derivative dimensions.
    $xxx_effects = $this->effects;  // @todo review variable name
    foreach ($this->text as $uuid => $text_item) {
      $xxx_effects[$uuid]['data']['text_string'] = $text_item;
    }
    $runtime_style = $this->buildStyleFromEffects($xxx_effects);
    if ($this->sourceImageFile) {
      $source_image = $this->imageFactory->get($this->sourceImageFile->getFileUri()); // @todo avoid if possible
      $dimensions = [
        'width' => $source_image->getWidth(),
        'height' => $source_image->getHeight(),
      ];
      $uri = $this->sourceImageFile->getFileUri();
    }
    else {
      $dimensions = [
        'width' => 1,
        'height' => 1,
      ];
      $uri = NULL;
    }
    $runtime_style->transformDimensions($dimensions, $uri);
    $this->width = $dimensions['width'];
    $this->height = $dimensions['height'];
    if (!$this->extension) {
      if ($this->sourceImageFile) {
        $this->extension = pathinfo($this->sourceImageFile->getFileUri(), PATHINFO_EXTENSION);
      }
      else {
        $this->extension = $this->config->get('default_extension');
      }
      $this->extension = $runtime_style->getDerivativeExtension($this->extension);
    }

    // Data for this textimage.
    $this->imageData = array(
      'text'                => $this->text,
      'extension'           => $this->extension,
      'sourceImage'         => $this->sourceImageFile ? $this->sourceImageFile->getFileUri() : NULL,
    );

    // Remove text from effects outline, as actual runtime text goes
    // separately to the hash.
    foreach ($this->effects as $uuid => &$effect_configuration) {
      if ($effect_configuration['id'] == 'textimage_text') {
        unset($effect_configuration['data']['text_string']);
      }
    }

    // Get SHA256 hash, being the Textimage id, for cache checking.
    $hash_input = array(
      'effects_outline'     => $this->effects,
      'image_data'          => $this->imageData,
    );
    $this->id = hash('sha256', serialize($hash_input));

    // Check cache and return if hit.
    if ($this->caching && ($cached_data = $this->getCachedData())) {
      $this->uri = $cached_data['uri'];
      $this->processed = TRUE;
      return $this;
    }
    else {
      // Not found, build the image.
      // Get URI of the to-be image file.
      if (!$this->uri) {
        $this->buildUri();
      }
      $this->processed = TRUE;
      if ($this->caching) {
        $this->setCached();
      }
    }

    return $this;
  }

  /**
   * Build the image via core ImageStyle::createDerivative() method.
   *
   * @return $this
   */
  public function buildImage() {
    // Do not proceed if not processed.
    if (!$this->processed) {
      throw new TextimageException("Attempted to build Textimage before processing data");
    }

    // Do not re-build.
    if ($this->built) {
      throw new TextimageException('Attempted to build an already built Textimage');
    }

    // Check cache and return if hit.
    if ($this->getCachedData() && is_file($this->uri)) {
      $this->logger->debug('Got Textimage from cache, @uri', ['@uri' => $this->uri]);
      return $this;
    }

    // Check file store and return if hit.
    if ($this->caching && is_file($this->uri)) {
      $this->logger->debug('Got Textimage from store, @uri', ['@uri' => $this->uri]);
      return $this;
    }

    // If no source image specified, we are processing a pure Textimage
    // request. In that case we create a new 1x1 image to ensure we start
    // with a clean background.
    $source = isset($this->sourceImageFile) ? $this->sourceImageFile->getFileUri() : NULL;
    $image = $this->imageFactory->get($source);
    if (!$source) {
      $image->createNew(1, 1, $this->extension, $this->gifTransparentColor);
    }

    // Reset state.
    $this->factory->setState();
    $this->factory->setState('building_module', 'textimage');

    // Try a lock to the file generation process. If cannot get the lock,
    // return success if the file exists already. Otherwise return failure.
    $lock_name = 'textimage_process:' . Crypt::hashBase64($this->uri);
    if(!$lock_acquired = $this->lock->acquire($lock_name)) {
      return file_exists($this->uri) ? TRUE : FALSE;
    }

    // Inject processed text in the textimage_text effects data, and build a
    // runtime-only style
    $runtime_effects = $this->effects;
    foreach ($this->text as $uuid => $text_item) {
      $runtime_effects[$uuid]['data']['text_string'] = $text_item;
    }
    $runtime_style = $this->buildStyleFromEffects($runtime_effects);

    // Manage change of file extension if needed.
    if ($this->sourceImageFile) {
      $runtime_extension = pathinfo($this->sourceImageFile->getFileUri(), PATHINFO_EXTENSION);
    }
    else {
      $runtime_extension = $this->extension;
    }
    $runtime_extension = $runtime_style->getDerivativeExtension($runtime_extension);
    if ($runtime_extension != $this->extension) {
      // Find the max weight from effects.
      $max_weight = NULL;
      foreach ($runtime_style->getEffects()->getConfiguration() as $effect_configuration) {
        if (!$max_weight || $effect_configuration['weight'] > $max_weight) {
          $max_weight = $effect_configuration['weight'];
        }
      }
      // Add an image_convert effect as last effect.
      $convert = [
        'id' => 'image_convert',
        'weight' => ++$max_weight,
        'data' => [
          'extension' => $this->extension,
        ],
      ];
      $runtime_style->addImageEffect($convert);
    }

    // Generate the image.
    if (!$this->processed = $this->createDerivativeFromImage($runtime_style, $image, $this->uri)) {
      if (isset($this->style)) {
        $this->logger->error('Textimage failed to build an image for image style \'@style\'.', ['@style' => $this->style->id()]);
      }
      else {
        $this->logger->error('Textimage failed to build an image.');
      }
    }
    $this->logger->debug('Built Textimage, @uri', ['@uri' => $this->uri]);

    // Release lock.
    if (!empty($lock_acquired)) {
      $this->lock->release($lock_name);
    }

    // Reset state.
    $this->factory->setState();

    $this->built = TRUE;
    return $this;
  }

  /**
   * Create the derivative image from the Image object.
   *
   * @todo (core) remove if #2359443 gets in
   */
  protected function createDerivativeFromImage($style, $image, $derivative_uri) {
    // Get the folder for the final location of this style.
    $directory = $this->fileSystem->dirname($derivative_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      $this->logger->error('Failed to create Textimage directory: %directory', array('%directory' => $directory));
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
        $this->logger->error('Cached image file %destination already exists. There may be an issue with your rewrite configuration.', array('%destination' => $derivative_uri));
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Set URI to image file.
   *
   * An appropriate directory structure is in place to support styled,
   * unstyled and uncached (temporary) image files:
   *
   * for images with a supporting image style (styled) -
   *   {style_wrapper}://textimage_store/cache/styles/{style}/{substr(file name, 1)}/{substr(file name, 2)}/{file name}.{extension}
   *
   * for images generated via direct theme (unstyled) -
   *   {default_wrapper}://textimage_store/cache/api/{substr(file name, 1)}/{substr(file name, 2)}/{file name}.{extension}
   *
   * for uncached, temporary -
   *   {default_wrapper}://textimage_store/temp/{file name}.{extension}
   */
  protected function buildUri() {
    // The file name will be the Textimage hash.
    if ($this->caching) {
      $base_name = $this->id . '.' . $this->extension;
      if ($this->style) {
        $scheme = $this->style->getThirdPartySetting('textimage', 'uri_scheme');
        $this->uri = $this->factory->getStorePath('/cache/styles/', $scheme) . $this->style->id() . '/' . substr($base_name, 0, 1) . '/' . substr($base_name, 0, 2) . '/' . $base_name;
      }
      else {
        $this->uri = $this->factory->getStorePath('/cache/api/') . substr($base_name, 0, 1) . '/' . substr($base_name, 0, 2) . '/' . $base_name;
      }
    }
    else {
      $base_name = hash('sha256', session_id() . microtime()) . '.' . $this->extension;
      $this->uri = $this->factory->getStorePath('/temp/') . $base_name;
    }
  }

  /**
   * Get cached Textimage data.
   *
   * @return bool
   *   TRUE if an existing image file can be used, FALSE if no hit
   */
  protected function getCachedData() {
    if ($cached = $this->cache->get('tiid:' . $this->id)) {
      return $cached->data;
    }
    return FALSE;
  }

  /**
   * Restore Textimage object properties from cached data.
   *
   * @param array $cached_data
   *   An array of data from a cache->data array.
   *
   * @return $this
   */
  protected function restoreFromCache($cached_data) {
    $this->processed = $cached_data['processed'];
    $this->imageData = $cached_data['imageData'];
    $this->uri = $cached_data['uri'];
    $this->width = $cached_data['width'];
    $this->height = $cached_data['height'];
    $this->effects = $cached_data['effects'];
    $this->text = $cached_data['text'];
    $this->extension = $cached_data['extension'];
    $this->gifTransparentColor = $cached_data['gifTransparentColor'];
    $this->caching = TRUE;
    $this->forcedUri = $cached_data['forcedUri'];
    $this->bubbleableMetadata = $cached_data['bubbleableMetadata'];
    return $this;
  }

  /**
   * Cache Textimage data.
   *
   * @return $this
   */
  protected function setCached() {
    if (isset($this->style) && $this->style->id()) {
      $tags = $this->style->getCacheTags();
    }
    else {
      $tags = [];
    }
    $data = [
      'processed' => $this->processed,
      'imageData' => $this->imageData,
      'uri' => $this->uri,
      'width' => $this->width,
      'height' => $this->height,
      'effects' => $this->effects,
      'text' => $this->text,
      'extension' => $this->extension,
      'gifTransparentColor' => $this->gifTransparentColor,
      'forcedUri' => $this->forcedUri,
      'bubbleableMetadata' => $this->bubbleableMetadata,
    ];
    $this->cache->set('tiid:' . $this->id, $data, Cache::PERMANENT, $tags);
    return $this;
  }

}
