<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\file\Entity\File;
use Drupal\textimage\TextimageException;

/**
 * Functional tests for Textimage API.
 *
 * @group Textimage
 */
class TextimageApiTest extends TextimageTestBase {

  /**
   * Test functionality of the API.
   */
  public function testTextimageApi() {

    // Add more effects to the style.
    $style_path = 'admin/config/media/image-styles/manage/textimage_test';
    $effect_edits = array();
    $effect_edits[] = [
      'effect' => 'textimage_background',
      'data' => [
        'data[background_image][mode]' => 'passthrough',
      ],
    ];
    $effect_edits[] = array(
      'effect' => 'textimage_text',
      'data' => array(
        'data[font][angle]' => '90',
        'data[font][color][container][hex]' => '#FF0000',
        'data[text_default][text_string]' => 'Eff 1',
      ),
    );
    $effect_edits[] = array(
      'effect' => 'textimage_text',
      'data' => array(
        'data[font][angle]' => '-90',
        'data[font][color][container][hex]' => '#00FF00',
        'data[text_default][text_string]' => 'Eff 2',
      ),
    );
    $effect_edits[] = array(
      'effect' => 'textimage_text',
      'data' => array(
        'data[font][angle]' => '45',
        'data[font][color][container][hex]' => '#0000FF',
        'data[text_default][text_string]' => 'Eff 3',
      ),
    );
    $effect_edits[] = array(
      'effect' => 'image_desaturate',
      'data' => array(),
    );
    $effect_edits[] = array(
      'effect' => 'image_scale_and_crop',
      'data' => array(
        'data[width]' => 120,
        'data[height]' => 121,
      ),
    );
    foreach ($effect_edits as $effect) {
      $this->drupalPostForm($style_path, array('new' => $effect['effect']), t('Add'));
      if (!empty($effect['data'])) {
        $this->drupalPostForm(NULL, $effect['data'], t('Add effect'));
      }
    }

    // Test Textimage API.
    $textimage = $this->textimageFactory->get();

    // Check API is accepting input, but not providing output, before process.
    $this->assertTextimageException(FALSE, array($textimage, 'styleByName'), array('textimage_test'));
    $this->assertTextimageException(FALSE, array($textimage, 'setCaching'), array(TRUE));
    $this->assertTextimageException(FALSE, array($textimage, 'user'), array($this->adminUser));
    $this->assertNull($textimage->id(), 'ID is not available');
    $this->assertNull($textimage->getUri(), 'URI is not available');
    $this->assertNull($textimage->getUrl(), 'URL is not available');
    $returned_text = $textimage->getText();
    $this->assertTrue(empty($returned_text), 'Processed text is not available');

    // Process Textimage.
    $text_array = array('bingo', 'bongo', 'tengo', 'tango');
    $expected_text_array = array('bingo', 'bongo', 'tengo', 'tango');
    $textimage->process($text_array);

    // Check API is providing output after processing.
    $this->assertNotNull($textimage->id(), 'ID is available');
    $this->assertNotNull($textimage->getUri(), 'URI is available');
    $this->assertNotNull($textimage->getUrl(), 'URL is available');
    $this->assertTrue($textimage->getText() == $expected_text_array, 'Processed text is available');

    // Check API is not allowing changes after processing.
    $this->assertTextimageException(TRUE, array($textimage, 'styleByName'), array('textimage_test'));
    $this->assertTextimageException(TRUE, array($textimage, 'effects'), array(array()));
    $this->assertTextimageException(TRUE, array($textimage, 'forceExtension'), array('png'));
    $this->assertTextimageException(TRUE, array($textimage, 'setCaching'), array(FALSE));
    $this->assertTextimageException(TRUE, array($textimage, 'user'), array($this->adminUser));
    $this->assertTextimageException(TRUE, array($textimage, 'setTargetUri'), array('public://textimage-testing/bingo-bongo.png'));

    // Get textimage_store entry.
    $stored_image = db_select('textimage_store', 'ic')
        ->fields('ic')
        ->condition('tiid', $textimage->id(), '=')
        ->execute()
        ->fetchAssoc();
    $image_data = unserialize($stored_image['image_data']);
    $effects_outline = unserialize($stored_image['effects_outline']);

    // Check processed text is stored in image data.
    $this->assertTrue($expected_text_array == $image_data['text'], 'Processed text stored in image data');

    // Check count of effects is as expected.
    $this->assertTrue(count($effects_outline) == 7, 'Expected number of effects in the outline');

    // Check processed text is not stored in the effects outline.
    foreach ($effects_outline as $effect) {
      if ($effect['id'] == 'textimage_text') {
        $this->assertTrue(!isset($effect['data']['text_string']), 'Processed text not stored in the effects outline');
      }
    }

    $text_array = array('bingox', 'bongox', 'tengox', 'tangox');
    $expected_text_array = array('bingox', 'bongox', 'tengox', 'tangox');

    $files = $this->drupalGetTestFiles('image');

    // Test forcing an extension different from source image file.
    // Get image-test.png
    $file = File::create((array) array_shift($files));
    $file->save();
    $textimage = $this->textimageFactory->get();
    $textimage
      ->styleByName('textimage_test')
      ->sourceImageFile($file)
      ->forceExtension('gif')
      ->process($text_array);
    $image = $this->container->get('image.factory')->get($textimage->getUri());
    $this->assertEqual('image/gif', $image->getMimeType());

    // Ensure output image file extension is consistent with source image.
    // Get image-test.gif
    $file = File::create((array) array_shift($files));
    $file->save();
    $textimage = $this->textimageFactory->get();
    $textimage
      ->styleByName('textimage_test')
      ->sourceImageFile($file)
      ->process($text_array);
    $image = $this->container->get('image.factory')->get($textimage->getUri());
    $this->assertEqual('image/gif', $image->getMimeType());

    // Test loading the Textimage metadata.
    $id = $textimage->id();
    $uri = $textimage->getUri();
    $textimage = $this->textimageFactory->get();
    $textimage
      ->load($id);
    // Check loaded data.
    $this->assertEqual($textimage->id(), $id, 'Load - ID correct');
    $this->assertEqual($textimage->getUri(), $uri, 'Load - URI correct');
    $this->assertEqual($textimage->getText(), $expected_text_array, 'Load - Text correct');
    $this->assertTextimageException(TRUE, array($textimage, 'styleByName'), array('textimage_test'));
    // File exists.
    $this->assertTrue(file_exists($uri), 'Load - file exixts');
    // File deletion.
    $this->assertTrue(file_unmanaged_delete($uri), 'Load - file was deleted');
    // Reload and rebuild.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->load($id);
    $this->assertTrue(file_exists($uri), 'Load - file exixts');

    // Test output of theme textimage_formatter.
    $output = array(
      '#theme' => 'textimage_formatter',
      '#textimage' => $textimage,
      '#alt' => 'Alternate text',
      '#title' => 'Textimage title',
      '#attributes' => array('class' => 'textimage-test'),
      '#image_container_attributes' => array('class' => ['textimage-container-test']),
      '#anchor_url' => $textimage->getUrl(),
    );
    $this->setRawContent($this->renderer->renderRoot($output));
    $this->verbose($this->getRawContent());
    $url = $textimage->getUrl();
    $elements = $this->cssSelect("a[href='$url'] div.textimage-container-test img[src='$url']");
    $this->assertTrue(!empty($elements), 'Textimage formatted correctly.');

    // Test targeting invalid URIs.
    $textimage = $this->textimageFactory->get();
    $this->assertTextimageException(TRUE, array($textimage, 'setTargetUri'), array('bingo://textimage-testing/bingo-bongo.png'));
    $this->assertTextimageException(TRUE, array($textimage, 'setTargetUri'), array('public://textimage-testing/bingo' . chr(1) . '.png'));
  }

  /**
   * Test changing image file extension via image effect.
   */
  public function testFileExtensionChange() {

    // Process, should generate a PNG image file.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->styleByName('textimage_test')
      ->setCaching(TRUE)
      ->process('bingo');
    $image = $this->container->get('image.factory')->get($textimage->getUri());
    $this->assertEqual('image/png', $image->getMimeType());

    // Add an extension change effect to the style.
    $style_path = 'admin/config/media/image-styles/manage/textimage_test';
    $effect_edits = [];
    $effect_edits[] = [
      'effect' => 'textimage_background',
      'data' => [
        'data[background_image][mode]' => 'passthrough',
        'data[format][extension]' => 'jpeg',
      ],
    ];
    foreach ($effect_edits as $effect) {
      $this->drupalPostForm($style_path, array('new' => $effect['effect']), t('Add'));
      if (!empty($effect['data'])) {
        $this->drupalPostForm(NULL, $effect['data'], t('Add effect'));
      }
    }

    // Process, should generate a JPEG image file.
    $textimage = $this->textimageFactory->get();
    $textimage
      ->styleByName('textimage_test')
      ->setCaching(TRUE)
      ->process('bingo');
    $image = $this->container->get('image.factory')->get($textimage->getUri());
    $this->assertEqual('image/jpeg', $image->getMimeType());

  }

  /**
   * Assert throwing of a TextimageException.
   */
  protected function assertTextimageException($expected, $callback, $param_arr) {
    try {
      call_user_func_array($callback, $param_arr);
      $this->assertTrue(!$expected, 'Exception not raised.');
    }
    catch (TextimageException $e) {
      $this->assertTrue($expected, $e->getMessage());
    }
  }

}
