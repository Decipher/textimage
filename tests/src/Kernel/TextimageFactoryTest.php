<?php

declare(strict_types=1);

namespace Drupal\Tests\textimage\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Textimage factory service.
 */
#[Group('textimage')]
#[RunTestsInSeparateProcesses]
class TextimageFactoryTest extends KernelTestBase {

  use TextimageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'file_mdm',
    'file_mdm_font',
    'image',
    'image_effects',
    'system',
    'textimage',
    'user',
    'vendor_stream_wrapper',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'system',
      'textimage',
      'image',
      'image_effects',
      'user',
      'file',
      'file_mdm',
      'file_mdm_font',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->initTextimageTest();
  }

  /**
   * Test detection of image styles that carry a text overlay effect.
   */
  public function testIsTextimage(): void {
    $this->assertTrue($this->textimageFactory->isTextimage(ImageStyle::load('textimage_test')));

    // A style with no text overlay effect is not a Textimage style.
    $style = ImageStyle::create(['name' => 'plain_style', 'label' => 'Plain style']);
    $style->addImageEffect(['id' => 'image_desaturate', 'data' => []]);
    $style->save();
    $this->assertFalse($this->textimageFactory->isTextimage($style));
  }

  /**
   * Test the image style options list.
   */
  public function testGetTextimageStyleOptions(): void {
    $style = ImageStyle::create(['name' => 'plain_style', 'label' => 'Plain style']);
    $style->addImageEffect(['id' => 'image_desaturate', 'data' => []]);
    $style->save();

    // All styles are returned by default.
    $all = $this->textimageFactory->getTextimageStyleOptions();
    $this->assertArrayHasKey('textimage_test', $all);
    $this->assertArrayHasKey('plain_style', $all);
    $this->assertSame('Plain style', $all['plain_style']);

    // Only Textimage styles are returned when limited.
    $limited = $this->textimageFactory->getTextimageStyleOptions(TRUE);
    $this->assertArrayHasKey('textimage_test', $limited);
    $this->assertArrayNotHasKey('plain_style', $limited);
  }

  /**
   * Test the store URI helper.
   */
  public function testGetStoreUri(): void {
    $this->assertSame('public://textimage_store/temp', $this->textimageFactory->getStoreUri('/temp'));
    $this->assertSame('private://textimage_store/temp', $this->textimageFactory->getStoreUri('/temp', 'private'));
  }

  /**
   * Test the state helpers.
   */
  public function testState(): void {
    $this->assertNull($this->textimageFactory->getState('bingo'));
    $this->textimageFactory->setState('bingo', 'bongo');
    $this->assertSame('bongo', $this->textimageFactory->getState('bingo'));
  }

  /**
   * Test that flushing all Textimages removes the store directories.
   */
  public function testFlushAll(): void {
    // Build a textimage so the store directory exists.
    $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->process('bingo')
      ->buildImage();
    $store = $this->textimageFactory->getStoreUri(NULL);
    $this->assertDirectoryExists($store);

    // The URL generation directory is removed as well.
    $url_directory = 'public://textimage';
    $this->fileSystem->prepareDirectory($url_directory, FileSystemInterface::CREATE_DIRECTORY);

    $this->textimageFactory->flushAll();
    $this->assertDirectoryDoesNotExist($store);
    $this->assertDirectoryDoesNotExist($url_directory);
  }

}
