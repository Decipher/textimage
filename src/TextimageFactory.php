<?php

declare(strict_types=1);

namespace Drupal\textimage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageEffectManager;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a factory for Textimage.
 */
class TextimageFactory implements TextimageFactoryInterface {

  /**
   * The User entity storage.
   */
  protected readonly EntityStorageInterface $userStorage;

  public function __construct(
    public readonly ConfigFactoryInterface $configFactory,
    protected readonly Token $token,
    public readonly TextimageLogger $logger,
    #[Autowire(service: 'cache.textimage')]
    public readonly CacheBackendInterface $cache,
    protected readonly AccountInterface $currentUser,
    public readonly StreamWrapperManagerInterface $streamWrapperManager,
    EntityTypeManagerInterface $entityTypeManager,
    public readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'lock')]
    public readonly LockBackendInterface $lock,
    public readonly ImageFactory $imageFactory,
    #[Autowire(service: 'plugin.manager.image.effect')]
    public readonly ImageEffectManager $imageEffectManager,
    public readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  public function get(?BubbleableMetadata $bubbleable_metadata = NULL): TextimageInterface {
    $textimage = new Textimage($this);
    $textimage->setBubbleableMetadata($bubbleable_metadata);
    return $textimage;
  }

  public function load(string $tiid): TextimageInterface {
    $textimage = $this->get();
    $textimage->load($tiid);
    return $textimage;
  }

  public function processTextString(string $text, array $token_data = [], ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    // Replace any tokens in text with run-time values.
    $token_data['user'] = !empty($token_data['user']) ? $token_data['user'] : $this->userStorage->load($this->currentUser->id());
    return $this->token->replace($text, $token_data, [], $bubbleable_metadata);
  }

  public function getState(?string $variable = NULL): mixed {
    if ($variable) {
      return $this->setState($variable);
    }
    return NULL;
  }

  public function setState(?string $variable = NULL, mixed $value = NULL): mixed {
    static $keys;

    if (!isset($keys) or !$variable) {
      $keys = [];
    }

    if ($variable) {
      if ($value) {
        $keys[$variable] = $value;
        return $value;
      }
      else {
        return $keys[$variable] ?? NULL;
      }
    }

    return NULL;
  }

  public function isTextimage(ImageStyleInterface $image_style): bool {
    foreach ($image_style->getEffects() as $effect) {
      $definition = $effect->getPluginDefinition();
      if ($definition['id'] == 'image_effects_text_overlay') {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function getTextimageStyleOptions(bool $limit_to_textimage = FALSE): array {
    $image_styles = ImageStyle::loadMultiple();
    $options = [];
    foreach ($image_styles as $name => $image_style) {
      if ($limit_to_textimage) {
        if ($this->isTextimage($image_style)) {
          $options[$name] = $image_style->label();
        }
      }
      else {
        $options[$name] = $image_style->label();
      }
    }
    return $options;
  }

  public function flushStyle(ImageStyleInterface $style): void {
    // Clear hashed filename images.
    $wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
    foreach ($wrappers as $wrapper => $wrapper_data) {
      if (file_exists($directory = $this->getStoreUri('/cache/styles/', $wrapper) . $style->id())) {
        $this->fileSystem->deleteRecursive($directory);
      }
    }
    // Clear public textimage directory.
    if (file_exists($directory = 'public://textimage/' . $style->id())) {
      $this->fileSystem->deleteRecursive($directory);
    }
  }

  public function flushAll(): void {
    // Flush Textimage relevant styles so to invalidate the image styles cache
    // tags.
    $styles = ImageStyle::loadMultiple();
    foreach ($styles as $style) {
      $style->flush();
    }
    // Clear whatever directory structure remains, checking in all available
    // schemes.
    $wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
    foreach ($wrappers as $wrapper => $wrapper_data) {
      if (file_exists($directory = $this->getStoreUri(NULL, $wrapper))) {
        $this->fileSystem->deleteRecursive($directory);
      }
    }
    // Remove the URL generation directory.
    if (file_exists($directory = 'public://textimage')) {
      $this->fileSystem->deleteRecursive($directory);
    }
    // Wipe Textimage cache.
    $this->cache->deleteAll();
    $this->logger->notice('All Textimage images were removed.');
  }

  public function getStoreUri(?string $path, ?string $scheme = NULL): string {
    if (!$scheme) {
      $scheme = $this->configFactory->get('system.file')->get('default_scheme');
    }
    return $scheme . '://textimage_store' . $path;
  }

  public function processTokens(string $key, array $tokens, array $data, BubbleableMetadata $bubbleable_metadata): array {

    // @todo Not only node?
    $node = $data['node'] ?? NULL;

    // Need to avoid endless loops, that would occur if there are
    // circular references in the tokens. Set static variables for
    // the nesting level and the stack of fields accessed so far.
    static $nesting_level;
    static $field_stack;
    if (!isset($nesting_level)) {
      $nesting_level = 0;
      $field_stack = [];
    }
    else {
      $nesting_level++;
    }

    // Get tokens specific for the required key.
    $sub_tokens = $this->token->findWithPrefix($tokens, $key);

    // Return immediately if none, or no node.
    if (empty($sub_tokens) || !$node) {
      $this->rollbackStack($nesting_level, $field_stack);
      return [];
    }

    // Loops through the tokens to resolve.
    $replacements = [];
    foreach ($sub_tokens as $sub_token => $original) {

      // Clear current nesting level field stack.
      unset($field_stack[$nesting_level]);

      // Get token elements.
      $sub_token_array = explode(':', $sub_token);

      // Get requested field name, continue if missing.
      $field_name = $sub_token_array[0];
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
      $display_mode = isset($sub_token_array[1]) ? ($sub_token_array[1] ?: 'default') : 'default';

      // Get requested sequence, default to NULL.
      $index = isset($sub_token_array[2]) ? (int) $sub_token_array[2] : NULL;

      // Get field info, continue if missing.
      if (!$field_info = $node->getFieldDefinition($field_name)) {
        continue;
      }

      // Get info on component providing formatting, continue if missing.
      $entity_display = EntityViewDisplay::load('node.' . $node->getType() . '.' . $display_mode);
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
      if (in_array($entity_display_component['type'], [
        'textimage_text_field_formatter',
        'textimage_image_field_formatter',
      ])) {

        // Get the image style used for the field formatting.
        $image_style_name = $entity_display_component['settings']['image_style'] ?? NULL;
        if (!$image_style_name) {
          continue;
        }
        $image_style = ImageStyle::load($image_style_name);

        // Get the field items.
        $items = $node->get($field_name);

        // Invoke Textimage API functions to return the token value requested.
        if (in_array($field_info->getFieldStorageDefinition()->getTypeProvider(), ['text', 'core'])) {
          $text = $this->getTextFieldText($items);
          if ($field_info->getFieldStorageDefinition()->getCardinality() != 1 && $entity_display_component['settings']['image_text_values'] == 'itemize') {
            // Build separate image for each text value.
            try {
              $ret = [];
              foreach ($text as $text_value) {
                $textimage = $this->get($bubbleable_metadata)
                  ->setStyle($image_style)
                  ->setTokenData($data)
                  ->process($text_value);
                $ret[] = $this->getTokenReplacement($textimage, $key);
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
                // Returns up in the nesting of iteration with the failing
                // token.
                $this->rollbackStack($nesting_level, $field_stack);
                throw new TextimageTokenException($e->getToken());
              }
              else {
                // Inform about the token failure.
                $this->logger->warning(
                  'Textimage token @token in node \'@node_title\' can not be resolved (circular reference). Remove the token to avoid this message.',
                  [
                    '@token' => $original,
                    '@node_title' => $node->getTitle(),
                  ]
                );
              }
            }
          }
          else {
            // Build single image with all text values.
            try {
              $textimage = $this->get($bubbleable_metadata)
                ->setStyle($image_style)
                ->setTokenData($data)
                ->process($text);
              $replacements[$original] = $this->getTokenReplacement($textimage, $key);
            }
            catch (TextimageTokenException $e) {
              // Callback ended up in circular loop, mark the failing token.
              $replacements[$original] = str_replace('textimage', 'void-textimage', $original);
              if ($nesting_level > 0) {
                // Returns up in the nesting of iteration with the failing
                // token.
                $this->rollbackStack($nesting_level, $field_stack);
                throw new TextimageTokenException($e->getToken());
              }
              else {
                // Inform about the token failure.
                $this->logger->warning(
                  'Textimage token @token in node \'@node_title\' can not be resolved (circular reference). Remove the token to avoid this message.',
                  [
                    '@token' => $original,
                    '@node_title' => $node->getTitle(),
                  ]
                );
              }
            }
          }
        }
        elseif ($field_info->getFieldStorageDefinition()->getTypeProvider() == 'image') {
          // Image field. Get a separate Textimage from each of the images
          // in the field.
          try {
            $ret = [];
            foreach ($items as $item) {
              // Get source image from the image field item.
              $item_value = $item->getValue();
              $textimage = $this->get($bubbleable_metadata)
                ->setStyle($image_style)
                ->setTokenData($data)
                ->setSourceImageFile($item->entity, (int) $item_value['width'], (int) $item_value['height'])
                ->process(NULL);
              $ret[] = $this->getTokenReplacement($textimage, $key);
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
              $this->logger->warning(
                'Textimage token @token in node \'@node_title\' can not be resolved (circular reference). Remove the token to avoid this message.',
                [
                  '@token' => $original,
                  '@node_title' => $node->getTitle(),
                ]
              );
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
   * Helper method to determine the token value in processTokens.
   */
  protected function getTokenReplacement(TextimageInterface $textimage, string $key): string {
    return match ($key) {
      'textimage-uri' => $textimage->getUri(),
      'textimage-url' => $textimage->getUrl()->toString(),
      default => throw new \InvalidArgumentException("Invalid token key '$key"),
    };
  }

  /**
   * Helper method to rollback nesting static variables in processTokens.
   */
  protected function rollbackStack(?int &$nesting_level, array &$field_stack): void {
    if ($nesting_level) {
      unset($field_stack[$nesting_level]);
      $nesting_level--;
    }
    else {
      $nesting_level = NULL;
    }
  }

  public function getTextFieldText(FieldItemListInterface $items): array {
    $text = [];
    foreach ($items as $item) {
      $value = $item->getValue();
      $text[] = !empty($value['value']) ? $value['value'] : '';
    }
    return $text;
  }

}
