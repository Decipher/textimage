<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

/**
 * Functional tests for Textimage.
 */
class TextimageTest extends TextimageTestBase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Textimage functionality',
      'description' => 'Basic functionality of the Textimage module',
      'group' => 'Textimage',
    );
  }

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
    $output = drupal_render($textimage);

    // Check files were generated.
    $files_count = count(file_scan_directory($directory_path . '/textimage/textimage_test', '/.*/'));
    $this->assertTrue($files_count == 4, t('Textimage generation via theme.'));

    // Build and display a URL derivative.
    $this->drupalGet($directory_path . '/textimage/textimage_test/url_preview_text_image');
    $this->assertResponse(200);

    // Check file was generated.
    $files_count = count(file_scan_directory($directory_path . '/textimage/textimage_test', '/.*/'));
    $this->assertTrue($files_count == 5, t('Textimage generation via request URL.'));

    // Build a textimage at target URI via API.
    $uri = $this->textimageFactory->getImageUri(
      'textimage_test',
      NULL,
      array('test'),
      'png',
      FALSE,
      NULL,
      NULL,
      'public://textimage-testing/bingo-bongo.png'
    );

    // Check file was generated.
    $files_count = count(file_scan_directory('public://textimage-testing', '/.*/'));
    $this->assertTrue($files_count == 1, t('Textimage generation at target URI via API.'));

    // Build another textimage at same target URI.
    $uri = $this->textimageFactory->getImageUri(
      'textimage_test',
      NULL,
      array('another test'),
      'png',
      FALSE,
      NULL,
      NULL,
      'public://textimage-testing/bingo-bongo.png'
    );

    // Check file was replaced.
    $files_count = count(file_scan_directory('public://textimage-testing', '/.*/'));
    $this->assertTrue($files_count == 1, t('Textimage replaced at target URI via API.'));

    // Build a textimage at target URI via theme.
    $textimage = array();
    $textimage[0] = array(
      '#theme' => 'textimage_formatter',
      '#style_name' => 'textimage_test',
      '#text' => array('Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.'),
      '#target_uri' => 'public://textimage-testing/ut-enim.png',
    );
    $output = drupal_render($textimage);

    // Check file was generated.
    $files_count = count(file_scan_directory('public://textimage-testing', '/.*/'));
    $this->assertTrue($files_count == 2, t('Textimage generation at target URI via theme.'));

  }

}
