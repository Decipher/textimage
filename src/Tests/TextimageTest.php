<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\Core\Cache\Cache;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;

/**
 * Basic functionality of the Textimage module.
 *
 * @group Textimage
 */
class TextimageTest extends TextimageTestBase {

  /**
   * Test functionality of the module.
   */
  public function testTextimage() {

    $config = \Drupal::service('config.factory')->get('system.file');
    $stream_wrapper = file_stream_wrapper_get_instance_by_scheme($config->get('default_scheme'));
    $directory_path = $stream_wrapper->getDirectoryPath();

    // Generate a few derivative images via theme.
    $textimage = array();
    $textimage[0] = array(
      '#theme' => 'textimage_formatter',
      '#style_name' => 'textimage_test',
      '#text' => array('preview text image'),
    );
    $textimage[1] = array(
      '#theme' => 'textimage_formatter',
      '#style_name' => 'textimage_test',
      '#text' => array('Предварительный просмотр текста'),
    );
    $textimage[2] = array(
      '#theme' => 'textimage_formatter',
      '#style_name' => 'textimage_test',
      '#text' => array('προεπισκόπηση της εικόνας κείμενο'),
    );
    $textimage[3] = array(
      '#theme' => 'textimage_formatter',
      '#style_name' => 'textimage_test',
      '#text' => array('Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.'),
    );
    $output = $this->renderer->renderRoot($textimage);

    // Check files were generated.
    $files_count = count(file_scan_directory($directory_path . '/textimage/textimage_test', '/.*/'));
    $this->assertTrue($files_count == 4, 'Textimage generation via theme.');
    $this->assertTextimage($directory_path . '/textimage/textimage_test/preview text image.png', 177, 28);
    $this->assertTextimage($directory_path . '/textimage/textimage_test/Предварительный просмотр текста.png', 331, 28);
    $this->assertTextimage($directory_path . '/textimage/textimage_test/προεπισκόπηση της εικόνας κείμενο.png', 328, 28);

    // Build and display a URL derivative.
    $this->drupalGet($directory_path . '/textimage/textimage_test/url_preview_text_image.png');
    $this->assertResponse(200);

    // Check file was generated.
    $files_count = count(file_scan_directory($directory_path . '/textimage/textimage_test', '/.*/'));
    $this->assertTrue($files_count == 5, 'Textimage generation via request URL.');
    $this->assertTextimage($directory_path . '/textimage/textimage_test/url_preview_text_image.png', 225, 28);

    // Build a textimage at target URI via API.
    $uri = $this->textimageFactory->get()
      ->styleByName('textimage_test')
      ->setTargetUri('public://textimage-testing/bingo-bongo.png')
      ->process('test')
      ->getUri();

    // Check file was generated.
    $files_count = count(file_scan_directory('public://textimage-testing', '/.*/'));
    $this->assertTrue($files_count == 1, 'Textimage generation at target URI via API.');
    $this->assertTextimage('public://textimage-testing/bingo-bongo.png', 35, 28);

    // Build another textimage at same target URI.
    $uri = $this->textimageFactory->get()
      ->styleByName('textimage_test')
      ->setTargetUri('public://textimage-testing/bingo-bongo.png')
      ->process('another test')
      ->getUri();

    // Check file was replaced.
    $files_count = count(file_scan_directory('public://textimage-testing', '/.*/'));
    $this->assertTrue($files_count == 1, 'Textimage replaced at target URI via API.');
    $this->assertTextimage('public://textimage-testing/bingo-bongo.png', 113, 28);

    // Build a textimage at target URI via theme.
    $textimage = array();
    $textimage[0] = array(
      '#theme' => 'textimage_formatter',
      '#style_name' => 'textimage_test',
      '#text' => array('Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.'),
      '#target_uri' => 'public://textimage-testing/ut-enim.png',
    );
    $output = $this->renderer->renderRoot($textimage);

    // Check file was generated.
    $files_count = count(file_scan_directory('public://textimage-testing', '/.*/'));
    $this->assertTrue($files_count == 2, 'Textimage generation at target URI via theme.');

    // Test token resolution.

    // Create a text field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $this->createTextimageField($field_name, 'article');

    // Create a new node.
    $field_value = $this->randomMachineName(20);
    $nid = $this->createTextimageNode($field_name, $field_value, 'article');
    $node = Node::load($nid);

    // Set the textimage formatter - no link.
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['type'] = 'textimage';
    $display_options['settings']['image_style'] = 'textimage_test';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);

    // Check token.
    $node = Node::load($nid);
    $uri = \Drupal::service('token')->replace('[textimage:uri:' . $field_name . ']', array('node' => $node));
    $this->assertEqual('public://textimage/textimage_test/' . $field_value . '.png', $uri);

    // Test caching.

    // From previous get, textimage is built.
    $this->assertRaw('Built Textimage');

    // Create another node with same data. Textimage should be got from cache.
    $this->createTextimageNode($field_name, $field_value, 'article');
    $this->assertRaw('Got Textimage from cache');

    // Invalidate tags for the ImageStyle.
    $image_style = ImageStyle::load('textimage_test');
    Cache::invalidateTags($image_style->getCacheTagsToInvalidate());

    // Create another node with same data. Textimage should be got from store.
    $this->createTextimageNode($field_name, $field_value, 'article');
    $this->assertRaw('Got Textimage from store');

  }

}
