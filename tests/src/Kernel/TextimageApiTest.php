<?php

namespace Drupal\Tests\textimage\Kernel;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\textimage\TextimageException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for Textimage API.
 */
#[Group('textimage')]
#[RunTestsInSeparateProcesses]
class TextimageApiTest extends KernelTestBase {

  use TextimageTestTrait;
  use TestFileCreationTrait;
  use UserCreationTrait;

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
   * An user account, to be used for token replacement.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
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

    // Create an user.
    $this->testUser = $this->createUser();
  }

  /**
   * Test basic functionality of the API.
   */
  public function testTextimageApi() {

    // Add more effects to the test style.
    $style = ImageStyle::load('textimage_test');
    $style->addImageEffect([
      'id' => 'image_effects_text_overlay',
      'data' => [
        'font' => [
          'name' => 'Linux Libertine',
          'uri' => 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf',
          'size' => 16,
          'angle' => '90',
          'color' => '#FF0000',
        ],
        'text_string' => 'Eff 1',
      ],
    ]);
    $style->addImageEffect([
      'id' => 'image_effects_text_overlay',
      'data' => [
        'font' => [
          'name' => 'Linux Libertine',
          'uri' => 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf',
          'size' => 16,
          'angle' => '-90',
          'color' => '#00FF00',
        ],
        'text_string' => 'Eff 2',
      ],
    ]);
    $style->addImageEffect([
      'id' => 'image_effects_text_overlay',
      'data' => [
        'font' => [
          'name' => 'Linux Libertine',
          'uri' => 'vendor://fileeye/linuxlibertine-fonts/LinLibertine_Rah.ttf',
          'size' => 16,
          'angle' => '45',
          'color' => '#0000FF',
        ],
        'text_string' => 'Eff 3',
      ],
    ]);
    $style->addImageEffect([
      'id' => 'image_desaturate',
      'data' => [],
    ]);
    $style->addImageEffect([
      'id' => 'image_scale_and_crop',
      'data' => [
        'width' => 120,
        'height' => 121,
      ],
    ]);
    $style->save();

    // Test Textimage API.
    $textimage = $this->textimageFactory->get();

    // Check API is accepting input, but not providing output, before process.
    $textimage->setStyle($style);
    $textimage->setTemporary(FALSE);
    $textimage->setTokenData(['user' => $this->testUser]);
    $this->assertNull($textimage->id(), 'ID is not available');
    $this->assertNull($textimage->getUri(), 'URI is not available');
    $this->assertNull($textimage->getUrl(), 'URL is not available');
    $this->assertNull($textimage->getBubbleableMetadata(), 'Bubbleable metadata is not available');
    $this->assertEmpty($textimage->getText(), 'Processed text is not available');
    try {
      $textimage->buildImage();
      $this->fail('buildImage() should have failed.');
    }
    catch (TextimageException $e) {
      // Continue.
    }

    // Process Textimage.
    $text_array = ['bingo', 'bongo', 'tengo', 'tango'];
    $expected_text_array = ['bingo', 'bongo', 'tengo', 'tango'];
    $textimage->process($text_array);

    // Check API is providing output after processing.
    $this->assertNotNull($textimage->id(), 'ID is available');
    $this->assertNotNull($textimage->getUri(), 'URI is available');
    $this->assertNotNull($textimage->getUrl(), 'URL is available');
    $this->assertNotNull($textimage->getBubbleableMetadata(), 'Bubbleable metadata is available');
    $this->assertSame($expected_text_array, $textimage->getText(), 'Processed text is available');

    // Build Textimage.
    $textimage->buildImage();

    // Check API is not allowing changes after processing.
    try {
      $textimage->setStyle($style);
      $this->fail('setStyle should have failed.');
    }
    catch (TextimageException $e) {
      // Countinue.
    }
    try {
      $textimage->setEffects([]);
      $this->fail('setEffects should have failed.');
    }
    catch (TextimageException $e) {
      // Countinue.
    }
    try {
      $textimage->setTargetExtension('png');
      $this->fail('setTargetExtension should have failed.');
    }
    catch (TextimageException $e) {
      // Countinue.
    }
    try {
      $textimage->setTemporary(TRUE);
      $this->fail('setTemporary should have failed.');
    }
    catch (TextimageException $e) {
      // Countinue.
    }
    try {
      $textimage->setTokenData(['user' => $this->testUser]);
      $this->fail('setTokenData should have failed.');
    }
    catch (TextimageException $e) {
      // Countinue.
    }
    try {
      $textimage->setTargetUri('public://textimage-testing/bingo-bongo.png');
      $this->fail('setTargetUri should have failed.');
    }
    catch (TextimageException $e) {
      // Countinue.
    }
    try {
      $textimage->process($text_array);
      $this->fail('Re-processed an already processed Textimage');
    }
    catch (TextimageException $e) {
      // Countinue.
    }

    // Get textimage cache entry.
    $stored_image = \Drupal::cache('textimage')->get('tiid:' . $textimage->id());
    $image_data = $stored_image->data['imageData'];
    $effects_outline = $stored_image->data['effects'];

    // Check processed text is stored in image data.
    $this->assertSame($expected_text_array, array_values($image_data['text']), 'Processed text stored in image data');

    // Check count of effects is as expected.
    $this->assertCount(6, $effects_outline, 'Expected number of effects in the outline');

    // Check processed text is not stored in the effects outline.
    foreach ($effects_outline as $effect) {
      if ($effect['id'] == 'image_effects_text_overlay') {
        $this->assertTrue(!isset($effect['data']['text_string']), 'Processed text not stored in the effects outline');
      }
    }
  }

  /**
   * Test forcing an extension different from source image file.
   */
  public function testForceTargetExtension() {
    $files = $this->getTestFiles('image');

    // Get 'image-test.png'.
    $file = File::create((array) array_shift($files));
    $file->save();

    // Force GIF.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setSourceImageFile($file)
      ->setTargetExtension('gif')
      ->process(['bingox'])
      ->buildImage();
    $image = \Drupal::service('image.factory')->get($textimage->getUri());
    $this->assertSame('image/gif', $image->getMimeType());
  }

  /**
   * Test output image file extension is consistent with source image.
   */
  public function testTargetExtension() {
    $files = $this->getTestFiles('image');

    // Get 'image-test.gif'.
    $file = File::create((array) $files[1]);
    $file->save();
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setSourceImageFile($file)
      ->process(['bingox'])
      ->buildImage();
    $image = \Drupal::service('image.factory')->get($textimage->getUri());
    $this->assertSame('image/gif', $image->getMimeType());

    // Test loading the Textimage metadata.
    $id = $textimage->id();
    $uri = $textimage->getUri();
    $textimage = $this->textimageFactory->load($id);
    $style = ImageStyle::load('textimage_test');

    // Check loaded data.
    $this->assertSame($id, $textimage->id());
    $this->assertSame($uri, $textimage->getUri());
    $this->assertSame(['bingox'], $textimage->getText());
    try {
      $textimage->setStyle($style);
      $this->fail('Property \'style\' set when image was processed already');
    }
    catch (TextimageException $e) {
      // Countinue.
    }

    // File exists.
    $this->assertFileExists($uri);
    // File deletion.
    $this->assertTrue($this->fileSystem->delete($uri));
    // Reload and rebuild.
    $textimage = $this->textimageFactory->load($id);
    $textimage->buildImage();
    $this->assertFileExists($uri);
  }

  /**
   * Test file extension casing.
   */
  public function testFileExtensionCasing() {
    // Ensure upper-casing in target image file extension is not a reason for
    // exceptions, and upper-cased extensions are lowered.
    // Get 'image-test.png' and rename to 'image-test.PNG'.
    $this->getTestFiles('image');
    $this->fileSystem->move('public://image-test.png', 'public://image-test.PNG');
    $file = File::create(['uri' => 'public://image-test.PNG']);
    $file->save();
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setSourceImageFile($file)
      ->setTargetExtension('PNG')
      ->process(['bingox'])
      ->buildImage();
    $image = \Drupal::service('image.factory')->get($textimage->getUri());
    $this->assertSame('image/png', $image->getMimeType());
    $this->assertSame('png', pathinfo($textimage->getUri(), PATHINFO_EXTENSION));
  }

  /**
   * Test changing image file extension via image effect.
   */
  public function testFileExtensionChange() {
    // Process, should generate a PNG image file.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('textimage_test'))
      ->process('bingo')
      ->buildImage();
    $image = \Drupal::service('image.factory')->get($textimage->getUri());
    $this->assertSame('image/png', $image->getMimeType());

    // Add an extension change effect to the style.
    $style = ImageStyle::load('textimage_test');
    $style->addImageEffect([
      'id' => 'image_convert',
      'data' => [
        'extension' => 'jpeg',
      ],
    ]);
    $style->save();

    // Process, should generate a JPEG image file.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('textimage_test'))
      ->process('bingo')
      ->buildImage();
    $image = \Drupal::service('image.factory')->get($textimage->getUri());
    $this->assertSame('image/jpeg', $image->getMimeType());
  }

  /**
   * Test text altering via the effect's alter hook.
   */
  public function testTextAlteration() {
    $effects = [];
    $effects[] = [
      'id' => 'image_effects_text_overlay',
      'data' => [
        'text' => [
          'strip_tags' => TRUE,
          'decode_entities' => TRUE,
          'maximum_chars' => 12,
          'excess_chars_text' => ' [more]',
          'case_format' => 'upper',
        ],
        'text_string' => 'Test preview',
      ],
    ];
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setEffects($effects)
      ->process('the quick brown fox jumps over the lazy dog');
    $this->assertSame(['THE QUICK BR [more]'], $textimage->getText());

    $effects = [];
    $effects[] = [
      'id' => 'image_effects_text_overlay',
      'data' => [
        'text' => [
          'strip_tags' => TRUE,
          'decode_entities' => TRUE,
          'case_format' => '',
          'maximum_chars' => NULL,
        ],
        'text_string' => 'Test preview',
      ],
    ];
    $textimage = $this->textimageFactory->get();
    $textimage
      ->setEffects($effects)
      ->process('<p>Para1</p><!-- Comment --> Para2');
    $this->assertSame(['Para1 Para2'], $textimage->getText());

    $textimage = $this->textimageFactory->get();
    $textimage
      ->setEffects($effects)
      ->process('&quot;Title&quot; One &hellip;');
    $this->assertSame(['"Title" One …'], $textimage->getText());
  }

  /**
   * Test targeting invalid URIs.
   */
  public function testSetInvalidTargetUriScheme() {
    $textimage = $this->textimageFactory->get();
    $this->expectException(TextimageException::class);
    $this->expectExceptionMessage('Textimage error: Invalid target URI \'bingo://textimage-testing/bingo-bongo.png\' specified');
    $textimage->setTargetUri('bingo://textimage-testing/bingo-bongo.png');
  }

  /**
   * Test targeting invalid URIs.
   */
  public function testSetInvalidTargetUriFilename() {
    $textimage = $this->textimageFactory->get();
    $this->expectException(TextimageException::class);
    $this->expectExceptionMessage('Textimage error: Invalid target URI \'public://textimage-testing/bingo' . chr(1) . '.png\' specified');
    $textimage->setTargetUri('public://textimage-testing/bingo' . chr(1) . '.png');
  }

}
