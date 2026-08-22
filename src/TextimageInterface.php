<?php

declare(strict_types=1);

namespace Drupal\textimage;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;

/**
 * Provides an interface for Textimage objects.
 */
interface TextimageInterface {

  /**
   * Set the image style.
   *
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to be used to derive the Textimage.
   */
  public function setStyle(ImageStyleInterface $image_style): static;

  /**
   * Set the image effects.
   *
   * @param array $effects
   *   An array of image effects. Since Textimage manipulates effects before
   *   rendering the image, the style effects are copied here to allow that.
   */
  public function setEffects(array $effects): static;

  /**
   * Sets the image file extension.
   *
   * @param string $extension
   *   The file extension to be used (e.g. jpeg/png/gif).
   */
  public function setTargetExtension(string $extension): static;

  /**
   * Set the RGB hex color to be used for GIF images.
   *
   * @param string $color
   *   The color to be used for transparent.
   */
  public function setGifTransparentColor(string $color): static;

  /**
   * Sets the image source file.
   *
   * @param \Drupal\file\FileInterface $source_image_file
   *   A file entity.
   * @param int|null $width
   *   (optional) The source image width if known. Defaults to NULL.
   * @param int|null $height
   *   (optional) The source image height if known. Defaults to NULL.
   */
  public function setSourceImageFile(FileInterface $source_image_file, ?int $width = NULL, ?int $height = NULL): static;

  /**
   * Sets the token data to resolve tokens.
   *
   * @param array $token_data
   *   An array of objects to resolve tokens.
   */
  public function setTokenData(array $token_data): static;

  /**
   * Set Textimage to be temporary.
   *
   * @param bool $is_temp
   *   FALSE if caching is required for this Textimage.
   */
  public function setTemporary(bool $is_temp): static;

  /**
   * Set image destination URI.
   *
   * @param string $uri
   *   A valid URI.
   */
  public function setTargetUri(string $uri): static;

  /**
   * Sets the bubbleable metadata.
   *
   * @param \Drupal\Core\Render\BubbleableMetadata|null $bubbleable_metadata
   *   A BubbleableMetadata object.
   *
   * @internal
   */
  public function setBubbleableMetadata(?BubbleableMetadata $bubbleable_metadata = NULL): static;

  /**
   * Return the Textimage id.
   *
   * @return string|null
   *   A SHA256 hash.
   */
  public function id(): ?string;

  /**
   * Return the processed text.
   *
   * @return array
   *   An array of fully processed text elements.
   */
  public function getText(): array;

  /**
   * Returns the URI of the Textimage.
   *
   * @return string|null
   *   An URI.
   */
  public function getUri(): ?string;

  /**
   * Returns the URL of the Textimage.
   *
   * @return \Drupal\Core\Url|null
   *   The Url object for the textimage.
   */
  public function getUrl(): ?Url;

  /**
   * Returns the height of the Textimage.
   *
   * @return int|null
   *   The height of the Textimage, or NULL if not available.
   */
  public function getHeight(): ?int;

  /**
   * Returns the width of the Textimage.
   *
   * @return int|null
   *   The width of the Textimage, or NULL if not available.
   */
  public function getWidth(): ?int;

  /**
   * Gets the bubbleable metadata of the Textimage.
   *
   * @return \Drupal\Core\Render\BubbleableMetadata|null
   *   A BubbleableMetadata object.
   */
  public function getBubbleableMetadata(): ?BubbleableMetadata;

  /**
   * Load Textimage metadata from cache.
   *
   * @param string $id
   *   The id of the Textimage to load.
   */
  public function load(string $id): static;

  /**
   * Process the Textimage, with the required raw text.
   *
   * @param array|string $text
   *   An array of text strings, or a single string, with tokens not resolved.
   */
  public function process(array|string|null $text): static;

  /**
   * Build the image via core ImageStyle::createDerivative() method.
   */
  public function buildImage(): static;

}
