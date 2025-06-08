<?php

namespace Drupal\Tests\textimage\Functional;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Basic functionality of the Textimage module.
 *
 * @group textimage
 */
class TextimageTest extends TextimageTestBase {

  use CronRunTrait;

  /**
   * Test functionality of the module.
   */
  public function testTextimage() {

    $publicStream = \Drupal::service('stream_wrapper_manager')->getViaScheme('public');
    assert($publicStream instanceof LocalStream);
    $public_directory_path = $publicStream->getDirectoryPath();

    $privateStream = \Drupal::service('stream_wrapper_manager')->getViaScheme('private');
    assert($privateStream instanceof LocalStream);
    $private_directory_path = $privateStream->getDirectoryPath();

    // Generate a few derivatives and render images via theme
    // 'textimage_formatter'.
    $input = [
      [
        'text' => ['preview text image'],
        'width' => 171,
        'height' => 24,
      ],
      [
        'text' => ['Предварительный просмотр текста'],
        'width' => 335,
        'height' => 24,
      ],
      [
        'text' => ['προεπισκόπηση της εικόνας κείμενο'],
        'width' => 325,
        'height' => 24,
      ],
      [
        'text' => ['Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'],
        'width' => 1104,
        'height' => 24,
      ],
    ];

    // Generate files on public.
    foreach ($input as $item) {
      $textimage = $this->textimageFactory->get()
        ->setStyle(ImageStyle::load('textimage_test'))
        ->process($item['text']);
      $element = [
        '#theme' => 'textimage_formatter',
        '#uri' => $textimage->getUri(),
        '#width' => $textimage->getWidth(),
        '#height' => $textimage->getHeight(),
      ];
      $textimage->getBubbleableMetadata()->applyTo($element);
      $this->renderer->renderRoot($element);
      $this->assertFileDoesNotExist($textimage->getUri());
      $this->drupalGet($textimage->getUrl());
      $this->assertFileExists($textimage->getUri());
      $this->assertTextimage($textimage->getUri(), $item['width'], $item['height']);
    }

    // Check that files were generated on public.
    $this->assertCount(4, $this->fileSystem->scanDirectory($public_directory_path . '/textimage_store/cache/styles/textimage_test', '/.*/'));

    // Check that cache entries were generated.
    foreach ($input as $item) {
      $textimage = $this->textimageFactory->get()
        ->setStyle(ImageStyle::load('textimage_test'))
        ->process($item['text']);
      $cached = \Drupal::cache('textimage')->get('tiid:' . $textimage->id());
      $this->assertSame($textimage->getUri(), $cached->data['uri']);
    }

    // Delete cache, files are still there upon re-processing, before
    // ::buildImage.
    \Drupal::cache('textimage')->deleteAll();
    foreach ($input as $item) {
      $textimage = $this->textimageFactory->get()
        ->setStyle(ImageStyle::load('textimage_test'))
        ->process($item['text']);
      $this->assertFileExists($textimage->getUri());
    }

    // Set image storage to 'private' wrapper.
    $this->drupalGet('admin/config/media/image-styles/manage/textimage_test');
    $this->submitForm(['textimage_options[uri_scheme]' => 'private'], 'Save');

    // Generate files on private.
    foreach ($input as $item) {
      $textimage = $this->textimageFactory->get()
        ->setStyle(ImageStyle::load('textimage_test'))
        ->process($item['text']);
      $element = [
        '#theme' => 'textimage_formatter',
        '#uri' => $textimage->getUri(),
        '#width' => $textimage->getWidth(),
        '#height' => $textimage->getHeight(),
      ];
      $textimage->getBubbleableMetadata()->applyTo($element);
      $this->renderer->renderRoot($element);
      $this->assertFileDoesNotExist($textimage->getUri());
      $this->drupalGet($textimage->getUrl());
      $this->assertFileExists($textimage->getUri());
      $this->assertTextimage($textimage->getUri(), $item['width'], $item['height']);
    }

    // Check that files were generated on private.
    $this->assertCount(4, $this->fileSystem->scanDirectory($private_directory_path . '/textimage_store/cache/styles/textimage_test', '/.*/'));

    // Try loading a missing Textimage ID, should fail with not found.
    $this->drupalGet($public_directory_path . '/textimage_store/cache/styles/textimage_test/8/8f/8f3f0c1a0d01c0487f97d068b2a77c792964eedfbe7e2f24eb1207429118aaff.png');
    $this->assertSession()->statusCodeEquals(404);

    // Test failure of a Textimage derivative via URL, on image style set to
    // private.
    $this->drupalGet($public_directory_path . '/textimage/textimage_test/url_preview_text_image---additional text.png');
    $this->assertSession()->statusCodeEquals(403);

    // Set image storage to 'public' wrapper.
    $this->drupalGet('admin/config/media/image-styles/manage/textimage_test');
    $this->submitForm(['textimage_options[uri_scheme]' => 'public'], 'Save');

    // Test build of a Textimage derivative via URL, on image style set to
    // public.
    $this->drupalGet($public_directory_path . '/textimage/textimage_test/url_preview_text_image---additional text.png');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(1, $this->fileSystem->scanDirectory($public_directory_path . '/textimage/textimage_test', '/.*/'), 'Textimage generation via request URL.');
    $this->assertTextimage('public://textimage/textimage_test/url_preview_text_image---additional text.png', 217, 24);

    // Test build a textimage at target URI via API.
    $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTargetUri('public://textimage-testing/bingo-bongo.png')
      ->process('test')
      ->buildImage();
    $this->assertCount(1, $this->fileSystem->scanDirectory('public://textimage-testing', '/.*/'), 'Textimage generation at target URI via API.');
    $this->assertTextimage('public://textimage-testing/bingo-bongo.png', 33, 24);

    // Test build another textimage at same target URI.
    $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTargetUri('public://textimage-testing/bingo-bongo.png')
      ->process('another test')
      ->buildImage();
    // Check file was replaced.
    $this->assertCount(1, $this->fileSystem->scanDirectory('public://textimage-testing', '/.*/'), 'Textimage replaced at target URI via API.');
    $this->assertTextimage('public://textimage-testing/bingo-bongo.png', 107, 24);
  }

  /**
   * Test execution of Textimage cron hook.
   */
  public function testTextimageCronRun() {
    // Build a temporary textimage via API.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTemporary(TRUE)
      ->process(['text image for cron run'])
      ->buildImage();

    // Temp file should be created at location.
    $this->assertCount(1, $this->fileSystem->scanDirectory('public://textimage_store/temp', '/.*/'));

    // Run cron.
    $this->cronRun();

    // Temp directory should be removed.
    $this->assertDirectoryDoesNotExist('public://textimage_store/temp');
  }

  /**
   * Test execution of Textimage settings form.
   */
  public function testSettingsForm(): void {
    $this->drupalGet($this->textimageAdmin);
    $this->assertSession()->statusCodeEquals(200);
  }

}
