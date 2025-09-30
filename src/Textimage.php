<?php

declare(strict_types=1);

namespace Drupal\textimage;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;

/**
 * Provides a Textimage.
 */
class Textimage implements TextimageInterface {

  use StringTranslationTrait;

  /**
   * Textimage id.
   *
   * It is a SHA256 hash of the textimage effects and data.
   *
   * @see \Drupal\textimage\Textimage::process()
   */
  protected ?string $id = NULL;

  /**
   * If data for this Textimage has been processed.
   */
  protected bool $processed = FALSE;

  /**
   * If this Textimage has been built.
   */
  protected bool $built = FALSE;

  /**
   * Textimage metadata.
   *
   * @var array
   */
  protected array $imageData = [];

  /**
   * Textimage URI.
   */
  protected ?string $uri = NULL;

  /**
   * Textimage width.
   */
  protected ?int $width = NULL;

  /**
   * Textimage height.
   */
  protected ?int $height = NULL;

  /**
   * Image style used for this Textimage.
   */
  protected ?ImageStyleInterface $style = NULL;

  /**
   * The array of image effects for this Textimage.
   *
   * @var array
   */
  protected array $effects = [];

  /**
   * The array of text elements for this Textimage.
   */
  protected array $text = [];

  /**
   * The file extension for this Textimage.
   */
  protected ?string $extension = NULL;

  /**
   * RGB hex color to be used for GIF images.
   *
   * Image effects may override this setting, this is here in case we build
   * a Textimage from scratch.
   */
  protected string $gifTransparentColor = '#FFFFFF';

  /**
   * If this Textimage has to be cached.
   */
  protected bool $caching = TRUE;

  /**
   * An image file entity.
   *
   * The source file used to build the image derivative in standard image
   * system context. Also used to track Textimages from image fields formatted
   * through Textimage field display formatter and to resolve file tokens.
   */
  protected ?FileInterface $sourceImageFile = NULL;

  /**
   * An array of objects to resolve tokens.
   */
  protected array $tokenData = [];

  /**
   * Bubbleable metadata of the Textimage.
   */
  protected ?BubbleableMetadata $bubbleableMetadata = NULL;

  public function __construct(
    protected readonly TextimageFactory $factory,
  ) {
  }

  /**
   * Set a property to a specified value.
   *
   * A Textimage already processed will not allow changes.
   *
   * @param string $property
   *   The property to set.
   * @param mixed $value
   *   The value to set.
   *
   * @return $this
   */
  protected function set(string $property, mixed $value): static {
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
   * {@inheritdoc}
   */
  public function setStyle(ImageStyleInterface $image_style): static {
    if ($this->style) {
      throw new TextimageException("Image style already set");
    }
    $this->set('style', $image_style);
    $effects = @$this->style->getEffects()->getConfiguration();
    $this->setEffects($effects);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEffects(array $effects): static {
    if ($this->effects) {
      throw new TextimageException("Image effects already set");
    }
    return $this->set('effects', $effects);
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetExtension(string $extension): static {
    if ($this->extension) {
      throw new TextimageException("Extension already set");
    }
    $extension = strtolower($extension);
    if (!in_array($extension, $this->factory->imageFactory->getSupportedExtensions())) {
      $this->factory->logger->error("Unsupported image file extension (%extension) requested.", ['%extension' => $extension]);
      throw new TextimageException("Attempted to set an unsupported file image extension ({$extension})");
    }
    return $this->set('extension', $extension);
  }

  /**
   * {@inheritdoc}
   */
  public function setGifTransparentColor(string $color): static {
    return $this->set('gifTransparentColor', $color);
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceImageFile(FileInterface $source_image_file, ?int $width = NULL, ?int $height = NULL): static {
    $this->set('sourceImageFile', $source_image_file);
    if ($width && $height) {
      $this->set('width', $width);
      $this->set('height', $height);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTokenData(array $token_data): static {
    if ($this->tokenData) {
      throw new TextimageException("Token data already set");
    }
    return $this->set('tokenData', $token_data);
  }

  /**
   * {@inheritdoc}
   */
  public function setTemporary(bool $is_temp): static {
    if ($this->uri) {
      throw new TextimageException("URI already set");
    }
    $this->set('caching', !$is_temp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetUri(string $uri): static {
    if ($this->uri) {
      throw new TextimageException("URI already set");
    }
    if ($uri) {
      if (!$this->factory->streamWrapperManager->isValidUri($uri)) {
        throw new TextimageException("Invalid target URI '{$uri}' specified");
      }
      $dir_name = $this->factory->fileSystem->dirname($uri);
      $base_name = basename($uri);
      $valid_uri = $this->createFilename($base_name, $dir_name);
      if ($uri != $valid_uri) {
        throw new TextimageException("Invalid target URI '{$uri}' specified");
      }
      $this->setTargetExtension(pathinfo($uri, PATHINFO_EXTENSION));
      $this->set('uri', $uri);
      $this->set('caching', FALSE);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setBubbleableMetadata(?BubbleableMetadata $bubbleable_metadata = NULL): static {
    if ($this->bubbleableMetadata) {
      throw new TextimageException("Bubbleable metadata already set");
    }
    $bubbleable_metadata = $bubbleable_metadata ?: new BubbleableMetadata();
    return $this->set('bubbleableMetadata', $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ?string {
    return $this->processed ? $this->id : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getText(): array {
    return $this->processed ? array_values($this->text) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUri(): ?string {
    return $this->processed ? $this->uri : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): ?Url {
    return $this->processed ? Url::fromUri($this->factory->fileUrlGenerator->generateAbsoluteString($this->getUri())) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight(): ?int {
    return $this->processed ? $this->height : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth(): ?int {
    return $this->processed ? $this->width : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBubbleableMetadata(): ?BubbleableMetadata {
    return $this->processed ? $this->bubbleableMetadata : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): static {
    // Do not re-process.
    if ($this->processed) {
      return $this;
    }
    // Load from the cache.
    $this->id = $id;
    if ($cached_data = $this->getCachedData()) {
      $this->set('imageData', $cached_data['imageData']);
      $this->set('uri', $cached_data['uri']);
      $this->set('width', $cached_data['width']);
      $this->set('height', $cached_data['height']);
      $this->set('effects', $cached_data['effects']);
      $this->set('text', $cached_data['imageData']['text']);
      $this->set('extension', $cached_data['imageData']['extension']);
      if ($cached_data['imageData']['sourceImageFileId']) {
        $this->set('sourceImageFile', File::load($cached_data['imageData']['sourceImageFileId']));
      }
      $this->set('gifTransparentColor', $cached_data['imageData']['gifTransparentColor']);
      $this->set('caching', TRUE);
      $this->set('bubbleableMetadata', $cached_data['bubbleableMetadata']);
      $this->processed = TRUE;
    }
    else {
      throw new TextimageException("Missing Textimage cache entry {$this->id}");
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function process(array|string|null $text): static {
    // Do not re-process.
    if ($this->processed) {
      throw new TextimageException("Attempted to re-process an already processed Textimage");
    }

    // Effects must be loaded.
    if (empty($this->effects)) {
      $this->factory->logger->error('Textimage had no image effects to process.');
      return $this;
    }

    // Collect bubbleable metadata.
    if ($this->style) {
      $this->bubbleableMetadata = $this->bubbleableMetadata->addCacheableDependency($this->style);
    }
    if ($this->sourceImageFile) {
      $this->bubbleableMetadata = $this->bubbleableMetadata->addCacheableDependency($this->sourceImageFile);
    }

    // Normalise $text to an array.
    if (!$text) {
      $text = [];
    }
    if (!is_array($text)) {
      $text = [$text];
    }

    // Find the default text from effects.
    $default_text = [];
    foreach ($this->effects as $uuid => $effect_configuration) {
      if ($effect_configuration['id'] == 'image_effects_text_overlay') {
        $uuid = $effect_configuration['uuid'] ?? $uuid;
        $default_text[$uuid] = $effect_configuration['data']['text_string'];
      }
    }

    // Process text to resolve tokens and required case conversions.
    $processed_text = [];
    $this->tokenData['file'] ??= $this->sourceImageFile;
    foreach ($default_text as $uuid => $default_text_item) {
      $text_item = array_shift($text);
      $effect_instance = $this->factory->imageEffectManager->createInstance($this->effects[$uuid]['id']);
      $effect_instance->setConfiguration($this->effects[$uuid]);
      if ($text_item) {
        // Replace any tokens in text with run-time values.
        $text_item = ($text_item == '[textimage:default]') ? $default_text_item : $text_item;
        $processed_text[$uuid] = $this->factory->processTextString($text_item, $this->tokenData, $this->bubbleableMetadata);
      }
      else {
        $processed_text[$uuid] = $this->factory->processTextString($default_text_item, $this->tokenData, $this->bubbleableMetadata);
      }
      // Let text be altered by the effect's alter hook.
      $processed_text[$uuid] = $effect_instance->getAlteredText($processed_text[$uuid]);
    }
    $this->text = $processed_text;

    // Set the output image file extension, and find derivative dimensions.
    $runtime_effects = $this->effects;
    foreach ($this->text as $uuid => $text_item) {
      $runtime_effects[$uuid]['data']['text_string'] = $text_item;
    }
    $runtime_style = $this->buildStyleFromEffects($runtime_effects);
    if ($this->sourceImageFile) {
      if ($this->width && $this->height) {
        $dimensions = [
          'width' => (int) $this->width,
          'height' => (int) $this->height,
        ];
      }
      else {
        // @todo (core) we need to take dimensions via image system as they are
        // not available from the file entity, see #1448124.
        $source_image = $this->factory->imageFactory->get($this->sourceImageFile->getFileUri());
        $dimensions = [
          'width' => (int) $source_image->getWidth(),
          'height' => (int) $source_image->getHeight(),
        ];
      }
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
    $this->set('width', $dimensions['width']);
    $this->set('height', $dimensions['height']);

    // Resolve image file extension.
    if (!$this->extension) {
      if ($this->sourceImageFile) {
        $extension = pathinfo($this->sourceImageFile->getFileUri(), PATHINFO_EXTENSION);
      }
      else {
        $extension = $this->factory->configFactory->get('textimage.settings')->get('default_extension');
      }
      $this->setTargetExtension($runtime_style->getDerivativeExtension($extension));
    }

    // Data for this textimage.
    $this->imageData = [
      'text'                => $this->text,
      'extension'           => $this->extension,
      'sourceImageFileId'   => $this->sourceImageFile ? $this->sourceImageFile->id() : NULL,
      'sourceImageFileUri'  => $this->sourceImageFile ? $this->sourceImageFile->getFileUri() : NULL,
      'gifTransparentColor' => $this->gifTransparentColor,
    ];

    // Remove text from effects outline, as actual runtime text goes
    // separately to the hash.
    foreach ($this->effects as $uuid => &$effect_configuration) {
      if ($effect_configuration['id'] == 'image_effects_text_overlay') {
        unset($effect_configuration['data']['text_string']);
      }
    }

    // Get SHA256 hash, being the Textimage id, for cache checking.
    $hash_input = [
      'effects_outline'     => $this->effects,
      'image_data'          => $this->imageData,
    ];
    $this->id = hash('sha256', serialize($hash_input));

    // Check cache and return if hit.
    if ($this->caching && ($cached_data = $this->getCachedData())) {
      $this->set('uri', $cached_data['uri']);
      $this->processed = TRUE;
      $this->factory->logger->debug('Cached Textimage, @uri', ['@uri' => $this->getUri()]);
      if (is_file($this->getUri())) {
        $this->built = TRUE;
      }
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
   * {@inheritdoc}
   */
  public function buildImage(): static {
    // Do not proceed if not processed.
    if (!$this->processed) {
      throw new TextimageException("Attempted to build Textimage before processing data");
    }

    // Do not re-build.
    if ($this->built) {
      return $this;
    }

    // Check file store and return if hit.
    if ($this->caching && is_file($this->getUri())) {
      $this->factory->logger->debug('Stored Textimage, @uri', ['@uri' => $this->getUri()]);
      return $this;
    }

    // If no source image specified, we are processing a pure Textimage
    // request. In that case we create a new 1x1 image to ensure we start
    // with a clean background.
    $source = isset($this->sourceImageFile) ? $this->sourceImageFile->getFileUri() : NULL;
    $image = $this->factory->imageFactory->get($source);
    if ($source === NULL) {
      $image->createNew(1, 1, $this->extension, $this->gifTransparentColor);
    }

    // Reset state.
    $this->factory->setState();
    $this->factory->setState('building_module', 'textimage');

    // Try a lock to the file generation process. If cannot get the lock,
    // return success if the file exists already. Otherwise return failure.
    $lock_name = 'textimage_process:' . Crypt::hashBase64($this->getUri());
    if (!$this->factory->lock->acquire($lock_name)) {
      if (file_exists($this->getUri())) {
        return $this;
      }
      throw new TextimageException("Textimage failed to acquire a lock to build an image");
    }

    // Inject processed text in the image_effects_text_overlay effects data,
    // and build a runtime-only style.
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
    if (!$this->processed = $this->createDerivativeFromImage($runtime_style, $image, $this->getUri())) {
      if (isset($this->style)) {
        throw new TextimageException("Textimage failed to build an image for image style '{$this->style->id()}'");
      }
      else {
        throw new TextimageException("Textimage failed to build an image");
      }
    }
    $this->factory->logger->debug('Built Textimage, @uri', ['@uri' => $this->getUri()]);

    // Release lock.
    $this->factory->lock->release($lock_name);

    // Reset state.
    $this->factory->setState();

    $this->built = TRUE;
    return $this;
  }

  /**
   * Builds an image style from an array of effects.
   *
   * The runtime style object does not get saved. It is used to be
   * passed to ImageStyle::createDerivative() to build an image derivative.
   *
   * @param array $effects
   *   An array of image effects.
   *
   * @return \Drupal\image\ImageStyleInterface
   *   An image style object.
   */
  protected function buildStyleFromEffects(array $effects): ImageStyleInterface {
    $style = ImageStyle::create([]);
    foreach ($effects as $effect) {
      $effect_instance = $this->factory->imageEffectManager->createInstance($effect['id']);
      $default_config = $effect_instance->defaultConfiguration();
      $effect['data'] = NestedArray::mergeDeep($default_config, $effect['data']);
      $style->addImageEffect($effect);
    }
    $style->getEffects()->sort();
    return $style;
  }

  /**
   * Creates a full file path from a directory and filename.
   *
   * Copied parts of file_create_filename() to avoid file existence check.
   *
   * @param string $basename
   *   String filename.
   * @param string $directory
   *   String containing the directory or parent URI.
   *
   * @return string
   *   File path consisting of $directory and a unique filename based off
   *   of $basename.
   */
  protected function createFilename(string $basename, string $directory): string {
    // Strip control characters (ASCII value < 32). Though these are allowed in
    // some filesystems, not many applications handle them well.
    $basename = preg_replace('/[\x00-\x1F]/u', '_', $basename);
    if (substr(PHP_OS, 0, 3) == 'WIN') {
      // These characters are not allowed in Windows filenames.
      $basename = str_replace([':', '*', '?', '"', '<', '>', '|'], '_', $basename);
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
   * Create the derivative image from the Image object.
   *
   * @todo (core) remove if #2359443 gets in
   */
  protected function createDerivativeFromImage(ImageStyleInterface $style, ImageInterface $image, string $derivative_uri): bool {
    // Get the folder for the final location of this style.
    $directory = $this->factory->fileSystem->dirname($derivative_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!$this->factory->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->factory->logger->error('Failed to create Textimage directory: %directory', ['%directory' => $directory]);
      return FALSE;
    }

    if (!$image->isValid()) {
      if ($image->getSource()) {
        $this->factory->logger->error("Invalid image at '%image'.", ['%image' => $image->getSource()]);
      }
      else {
        $this->factory->logger->error("Invalid source image.");
      }
      return FALSE;
    }

    foreach ($style->getEffects() as $effect) {
      $effect->applyEffect($image);
    }

    if (!$image->save($derivative_uri)) {
      if (file_exists($derivative_uri)) {
        $this->factory->logger->error('Cached image file %destination already exists. There may be an issue with your rewrite configuration.', ['%destination' => $derivative_uri]);
      }
      return FALSE;
    }

    return TRUE;
  }

  // @codingStandardsIgnoreStart
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
   *
   * @return $this
   */
  protected function buildUri(): static {
  // @codingStandardsIgnoreEnd
    // The file name will be the Textimage hash.
    if ($this->caching) {
      $base_name = $this->id . '.' . $this->extension;
      if ($this->style) {
        $scheme = $this->style->getThirdPartySetting('textimage', 'uri_scheme', $this->factory->configFactory->get('system.file')->get('default_scheme'));
        $this->set('uri', $this->factory->getStoreUri('/cache/styles/', $scheme) . $this->style->id() . '/' . substr($base_name, 0, 1) . '/' . substr($base_name, 0, 2) . '/' . $base_name);
      }
      else {
        $this->set('uri', $this->factory->getStoreUri('/cache/api/') . substr($base_name, 0, 1) . '/' . substr($base_name, 0, 2) . '/' . $base_name);
      }
    }
    else {
      $base_name = hash('sha256', session_id() . microtime()) . '.' . $this->extension;
      $this->set('uri', $this->factory->getStoreUri('/temp/') . $base_name);
    }
    return $this;
  }

  /**
   * Get cached Textimage data.
   *
   * @return array|false
   *   The cached data, or FALSE if no hit
   */
  protected function getCachedData(): array|false {
    if ($cached = $this->factory->cache->get('tiid:' . $this->id)) {
      return $cached->data;
    }
    return FALSE;
  }

  /**
   * Cache Textimage data.
   *
   * @return $this
   */
  protected function setCached(): static {
    $data = [
      'imageData' => $this->imageData,
      'uri' => $this->getUri(),
      'width' => $this->getWidth(),
      'height' => $this->getHeight(),
      'effects' => $this->effects,
      'bubbleableMetadata' => $this->getBubbleableMetadata(),
    ];
    $this->factory->cache->set('tiid:' . $this->id, $data, Cache::PERMANENT, $this->getBubbleableMetadata()->getCacheTags());
    return $this;
  }

}
