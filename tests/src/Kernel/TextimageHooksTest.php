<?php

declare(strict_types=1);

namespace Drupal\Tests\textimage\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\textimage\Hook\TextimageHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the Textimage hook implementations.
 */
#[Group('textimage')]
#[RunTestsInSeparateProcesses]
class TextimageHooksTest extends KernelTestBase {

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
   * The hook implementations under test.
   */
  protected TextimageHooks $hooks;

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
    $this->hooks = \Drupal::service(TextimageHooks::class);
  }

  /**
   * Test that help text is returned for the settings route only.
   */
  public function testHelp(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $help = $this->hooks->help('textimage.settings', $route_match);
    $this->assertIsString($help);
    $this->assertStringContainsString('Textimage provides integration', $help);
    $this->assertNull($this->hooks->help('system.admin', $route_match));
  }

  /**
   * Test that the cache flush hook declares the textimage bin.
   */
  public function testCacheFlush(): void {
    $this->assertSame(['textimage'], $this->hooks->cacheFlush());
  }

  /**
   * Test that token info declares the Textimage node tokens.
   */
  public function testTokenInfo(): void {
    $info = $this->hooks->tokenInfo();
    $this->assertArrayHasKey('textimage-uri', $info['tokens']['node']);
    $this->assertArrayHasKey('textimage-url', $info['tokens']['node']);
  }

  /**
   * Test that tokens are only processed for nodes.
   */
  public function testTokensForOtherEntityTypes(): void {
    $bubbleable_metadata = new BubbleableMetadata();
    $this->assertSame([], $this->hooks->tokens('user', ['whatever' => '[user:whatever]'], [], [], $bubbleable_metadata));
  }

  /**
   * Test that an image style gets the default URI scheme on presave.
   */
  public function testImageStylePresave(): void {
    $style = ImageStyle::create(['name' => 'presave_test', 'label' => 'Presave test']);
    $this->hooks->imageStylePresave($style);
    $this->assertSame('public', $style->getThirdPartySetting('textimage', 'uri_scheme'));
  }

  /**
   * Test that file download returns headers for a textimage file only.
   */
  public function testFileDownload(): void {
    // A file outside the textimage directories is not handled.
    $this->assertNull($this->hooks->fileDownload('public://not-textimage/bingo.png'));

    // Build a textimage so a real derivative file exists.
    $textimage = $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->process('bingo')
      ->buildImage();
    $uri = $textimage->getUri();
    $this->assertIsString($uri);

    // Copy it below a 'textimage' target path, which the hook controls.
    $directory = 'public://textimage/kernel-test';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $target = $directory . '/bingo.png';
    $this->fileSystem->copy($uri, $target, FileExists::Replace);
    $headers = $this->hooks->fileDownload($target);
    $this->assertIsArray($headers);
    $this->assertArrayHasKey('Content-Type', $headers);
    $this->assertArrayHasKey('Content-Length', $headers);
  }

  /**
   * Test that cron removes the temporary textimage directory.
   */
  public function testCron(): void {
    $directory = $this->textimageFactory->getStoreUri('/temp');
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $this->assertDirectoryExists($directory);
    $this->hooks->cron();
    $this->assertDirectoryDoesNotExist($directory);
  }

}
