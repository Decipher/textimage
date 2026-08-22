<?php

declare(strict_types=1);

namespace Drupal\Tests\textimage\Kernel;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\textimage\PathProcessor\TextimagePathProcessor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel tests for the Textimage inbound path processor.
 */
#[Group('textimage')]
#[RunTestsInSeparateProcesses]
class TextimagePathProcessorTest extends KernelTestBase {

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
   * The path processor under test.
   */
  protected TextimagePathProcessor $pathProcessor;

  /**
   * The public files directory path.
   */
  protected string $publicPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'textimage', 'image', 'image_effects']);
    $this->pathProcessor = \Drupal::service(TextimagePathProcessor::class);
    $stream = \Drupal::service(StreamWrapperManagerInterface::class)->getViaScheme('public');
    assert($stream instanceof LocalStream);
    $this->publicPath = $stream->getDirectoryPath();
  }

  /**
   * Test that a deferred public scheme path sets the file query parameter.
   */
  public function testPublicStorePath(): void {
    $request = Request::create('/');
    $path = $this->pathProcessor->processInbound('/' . $this->publicPath . '/textimage_store/cache/styles/foo/bingo.png', $request);
    $this->assertSame('/' . $this->publicPath . '/textimage_store', $path);
    $this->assertSame('cache/styles/foo/bingo.png', $request->query->get('file'));
  }

  /**
   * Test that a deferred private scheme path sets the file query parameter.
   */
  public function testPrivateStorePath(): void {
    $request = Request::create('/');
    $path = $this->pathProcessor->processInbound('/system/files/textimage_store/cache/styles/foo/bingo.png', $request);
    $this->assertSame('/system/files/textimage_store', $path);
    $this->assertSame('cache/styles/foo/bingo.png', $request->query->get('file'));
  }

  /**
   * Test that a URL generation path sets the text query parameter.
   */
  public function testUrlGenerationPath(): void {
    $request = Request::create('/');
    $path = $this->pathProcessor->processInbound('/' . $this->publicPath . '/textimage/my_style/some text', $request);
    $this->assertSame('/' . $this->publicPath . '/textimage/my_style', $path);
    $this->assertSame('some text', $request->query->get('text'));
  }

  /**
   * Test that a URL generation path without text is left alone.
   */
  public function testUrlGenerationPathWithoutText(): void {
    $request = Request::create('/');
    $path = '/' . $this->publicPath . '/textimage/my_style';
    $this->assertSame($path, $this->pathProcessor->processInbound($path, $request));
    $this->assertNull($request->query->get('text'));
  }

  /**
   * Test that an unrelated path is returned unchanged.
   */
  public function testUnrelatedPath(): void {
    $request = Request::create('/');
    $path = '/node/1';
    $this->assertSame($path, $this->pathProcessor->processInbound($path, $request));
    $this->assertNull($request->query->get('file'));
  }

}
