<?php

declare(strict_types=1);

namespace Drupal\Tests\textimage\Kernel;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\textimage\TextimageFactoryInterface;

/**
 * Trait to manage Textimage setup tasks common across tests.
 */
trait TextimageTestTrait {

  /**
   * The Textimage factory service.
   */
  protected TextimageFactoryInterface $textimageFactory;

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The entity display repository service.
   */
  protected EntityDisplayRepositoryInterface $entityDisplayRepository;

  /**
   * The module exetnsion list service.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * The file URL generator service.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * Common test initialization tasks.
   */
  public function initTextimageTest(): void {
    // Load services.
    $this->textimageFactory = \Drupal::service(TextimageFactoryInterface::class);
    $this->renderer = \Drupal::service(RendererInterface::class);
    $this->fileSystem = \Drupal::service(FileSystemInterface::class);
    $this->entityDisplayRepository = \Drupal::service(EntityDisplayRepositoryInterface::class);
    $this->moduleList = \Drupal::service(ModuleExtensionList::class);
    $this->fileUrlGenerator = \Drupal::service(FileUrlGeneratorInterface::class);

    // Change Image Effects settings.
    $config = \Drupal::configFactory()->getEditable('image_effects.settings');
    $config
      ->set('image_selector.plugin_id', 'dropdown')
      ->set('image_selector.plugin_settings.dropdown.path', $this->moduleList->getPath('image_effects') . '/tests/images')
      ->set('font_selector.plugin_id', 'dropdown')
      ->set('font_selector.plugin_settings.dropdown.path', 'vendor://fileeye/linuxlibertine-fonts')
      ->save();

    // Change Textimage settings.
    $config = \Drupal::configFactory()->getEditable('textimage.settings');
    $config
      ->set('url_generation.enabled', TRUE)
      ->set('debug', TRUE)
      ->set('default_font.name', 'Linux Libertine')
      ->set('default_font.uri', 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf')
      ->save();

    // Create a test image style, with a image_effects_text_overlay effect.
    $style = ImageStyle::create([
      'name' => 'textimage_test',
      'label' => 'Textimage Test',
    ]);
    $style->addImageEffect([
      'id' => 'image_effects_text_overlay',
      'data' => [
        'font' => [
          'name' => 'Linux Libertine',
          'uri' => 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf',
          'size' => 16,
          'angle' => 0,
          'color' => '#000000FF',
          'stroke_mode' => 'outline',
          'stroke_color' => '#000000FF',
          'outline_top' => 0,
          'outline_right' => 0,
          'outline_bottom' => 0,
          'outline_left' => 0,
          'shadow_x_offset' => 1,
          'shadow_y_offset' => 1,
          'shadow_width' => 0,
          'shadow_height' => 0,
        ],
        'layout' => [
          'padding_top' => 0,
          'padding_right' => 0,
          'padding_bottom' => 0,
          'padding_left' => 0,
          'x_pos' => 'center',
          'y_pos' => 'center',
          'x_offset' => 0,
          'y_offset' => 0,
          'background_color' => '#FFFFFF',
          'overflow_action' => 'extend',
          'extended_color' => NULL,
        ],
        'text' => [
          'strip_tags' => TRUE,
          'decode_entities' => TRUE,
          'maximum_width' => 0,
          'fixed_width' => FALSE,
          'align' => 'left',
          'line_spacing' => 0,
          'case_format' => '',
          'maximum_chars' => NULL,
          'excess_chars_text' => '…',
        ],
        'text_string'             => 'Test preview',
      ],
    ]);
    $style->save();
  }

  /**
   * Asserts a Textimage.
   */
  protected function assertTextimage(string $path, int $width, int $height): void {
    $image = \Drupal::service('image.factory')->get($path);
    $w_error = abs($image->getWidth() - $width);
    $h_error = abs($image->getHeight() - $height);
    $tolerance = 0.1;
    $this->assertTrue($w_error < $width * $tolerance && $h_error < $height * $tolerance, "Textimage {$path} width and height ({$image->getWidth()}x{$image->getHeight()}) approximate expected results ({$width}x{$height})");
  }

  /**
   * Returns the URI of a Textimage based on style name and text.
   */
  protected function getTextimageUriFromStyleAndText(string $style_name, array|string|null $text): string {
    return $this->textimageFactory->get()
      ->setStyle(ImageStyle::load($style_name))
      ->process($text)
      ->getUri();
  }

  /**
   * Returns the Url object of a Textimage based on style name and text.
   */
  protected function getTextimageUrlFromStyleAndText(string $style_name, array|string|null $text): Url {
    return $this->textimageFactory->get()
      ->setStyle(ImageStyle::load($style_name))
      ->process($text)
      ->getUrl();
  }

}
