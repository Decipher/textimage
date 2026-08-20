<?php

declare(strict_types=1);

namespace Drupal\textimage\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\image\ImageStyleInterface;
use Drupal\textimage\TextimageFactoryInterface;
use Drupal\textimage\TextimageLogger;

/**
 * Hook implementations for textimage.
 */
class TextimageHooks {

  use StringTranslationTrait;

  /**
 * Implements hook_help().
 */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'textimage.settings':
        $output = '<p>';
        $output .= $this->t('Textimage provides integration with the <a href="@image_effects_url">Image effects</a> module to generate images with overlaid text.', ['@image_effects_url' => 'https://www.drupal.org/project/image_effects']);
        $output .= ' ' . $this->t('Use <a href="@image">Image styles</a> features to create Image styles. The \'Text overlay\' image effect must be used to specify the text appearance on the generated image.', ['@image' => Url::fromRoute('entity.image_style.collection')->toString()]);
        $output .= ' ' . $this->t('On the edit image style form, a "Textimage options" section allows selecting Textimage-specific options for the style.');
        $output .= '</p>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_file_download().
   *
   * Control the access to files underneath the textimage directories.
   */
  #[Hook('file_download')]
  public function fileDownload(string $uri): int|array|null {
    $path = \Drupal::service(StreamWrapperManagerInterface::class)->getTarget($uri);
    // Private file access for image style derivatives.
    if (strpos($path, 'textimage') === 0) {
      // Check that the file exists and is an image.
      $image = \Drupal::service(ImageFactory::class)->get($uri);
      if ($image->isValid()) {
        return [
          // Send headers describing the image's size, and MIME-type...
          'Content-Type' => $image->getMimeType(),
          'Content-Length' => $image->getFileSize(),
          // By not explicitly setting them here, this uses normal Drupal
          // Expires, Cache-Control and ETag headers to prevent proxy or
          // browser caching of private images.
        ];
      }
      return -1;
    }
    return NULL;
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Remove temporary, uncached, image files in all available schemes.
    $wrappers = \Drupal::service(StreamWrapperManagerInterface::class)->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
    foreach ($wrappers as $wrapper => $wrapper_data) {
      if (file_exists($directory = \Drupal::service(TextimageFactoryInterface::class)->getStoreUri('/temp', $wrapper))) {
        if (\Drupal::service(FileSystemInterface::class)->deleteRecursive($directory)) {
          \Drupal::service(TextimageLogger::class)->notice('Textimage temporary image files removed.');
        }
        else {
          \Drupal::service(TextimageLogger::class)->error('Textimage could not remove temporary image files.');
        }
      }
    }
  }

  /**
   * Implements hook_cache_flush().
   */
  #[Hook('cache_flush')]
  public function cacheFlush(): array {
    return ['textimage'];
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $node_tokens = [
      'textimage-uri' => [
        'name' => $this->t("Textimage URI"),
        'description' => $this->t("The URI(s) of a Textimage generated for a node's field. Use like <strong>[node:textimage-uri:field{:display}{:sequence}]</strong>, where:<br/><strong>field</strong> is the machine name of the field for which the Textimage is generated (e.g. body or field_xxxx);<br/><strong>display</strong> is an optional indication of the display view mode (e.g. default, full, teaser, etc.); 'default' is used if not specified; <br/><strong>sequence</strong> is an optional indication of the URI to return if Textimage produces more images for the same field (like e.g. in a multi-value Image field); if not specified, a comma-delimited string of all the URIs generated will be returned."),
        'dynamic' => TRUE,
      ],
      'textimage-url' => [
        'name' => $this->t("Textimage URL"),
        'description' => $this->t("The URL(s) of a Textimage generated for a node's field. Use like <strong>[node:textimage-url:field{:display}{:sequence}]</strong>, where:<br/><strong>field</strong> is the machine name of the field for which the Textimage is generated (e.g. body or field_xxxx);<br/><strong>display</strong> is an optional indication of the display view mode (e.g. default, full, teaser, etc.); 'default' is used if not specified; <br/><strong>sequence</strong> is an optional indication of the URL to return if Textimage produces more images for the same field (like e.g. in a multi-value Image field); if not specified, a comma-delimited string of all the URLs generated will be returned."),
        'dynamic' => TRUE,
      ],
    ];
    return [
      'tokens' => ['node' => $node_tokens],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    if ($type === 'node') {
      // Process tokens.
      $replacements = \Drupal::service(TextimageFactoryInterface::class)->processTokens('textimage-url', $tokens, $data, $bubbleable_metadata);
      $replacements += \Drupal::service(TextimageFactoryInterface::class)->processTokens('textimage-uri', $tokens, $data, $bubbleable_metadata);
      return $replacements;
    }
    return [];
  }

  /**
   * Implements hook_image_style_flush().
   */
  #[Hook('image_style_flush')]
  public function imageStyleFlush(ImageStyleInterface $style, ?string $path = NULL): void {
    // Manage the textimage part of image style flushing.
    \Drupal::service(TextimageFactoryInterface::class)->flushStyle($style);
  }

  /**
   * Implements hook_image_style_presave().
   *
   * Deals with Textimage third party settings.
   */
  #[Hook('image_style_presave')]
  public function imageStylePresave(ImageStyleInterface $style): void {
    // If Textimage TPSs are not yet set, set defaults.
    if (!in_array('textimage', $style->getThirdPartyProviders())) {
      $style->setThirdPartySetting('textimage', 'uri_scheme', \Drupal::service(ConfigFactoryInterface::class)->get('system.file')->get('default_scheme'));
    }
  }

  /**
   * Make alterations to the core 'image_style_form' form.
   *
   * A fieldset is added if the image style is Textimage relevant.
   */
  #[Hook('form_image_style_form_alter')]
  public function formImageStyleFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $formObject->getEntity();
    $form['textimage_options'] = [
      '#type' => 'details',
      '#weight' => 10,
      '#tree' => TRUE,
      '#open' => FALSE,
      '#title' => $this->t('Textimage options'),
      '#description' => $this->t('Define Textimage options specific for this image style.'),
    ];
    // Define file storage wrapper used for the style images.
    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $form['textimage_options']['uri_scheme'] = [
      '#type' => 'radios',
      '#options' => $scheme_options,
      '#title' => $this->t('Image destination'),
      '#description' => $this->t('Select where Textimage image files should be stored. Private file storage has significantly more overhead than public files, but allows access restriction.'),
      '#default_value' => $image_style->getThirdPartySetting('textimage', 'uri_scheme', \Drupal::service(ConfigFactoryInterface::class)->get('system.file')->get('default_scheme')),
    ];
    // Adds a validate handler to deal with textimage options.
    $form['#validate'][] = [$this, 'formImageStyleFormValidate'];
  }

  /**
   * Submit handler to deal with the altered 'image_style_form' form.
   */
  public function formImageStyleFormValidate(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\EntityFormInterface $formObject */
    $formObject = $form_state->getFormObject();
    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $formObject->getEntity();
    $image_style->setThirdPartySetting('textimage', 'uri_scheme', $form_state->getValue(
      ['textimage_options', 'uri_scheme']
    ));
  }

}
