<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Functional tests for Textimage.
 */
class TextimageTest extends WebTestBase {

  protected $textimageAdmin = 'admin/config/media/textimage';
  protected $textimageFactory;

  public static $modules = array('textimage');

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
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->textimageFactory = \Drupal::service('textimage.factory');
  }

  /**
   * Test functionality of the module.
   */
  public function testTextimage() {

    $config = \Drupal::service('config.factory')->get('system.file');
    $stream_wrapper = file_stream_wrapper_get_instance_by_scheme($config->get('default_scheme'));
    $directory_path = $stream_wrapper->getDirectoryPath();

    // Create a user and log it in.
    $this->admin_user = $this->drupalCreateUser(array(
      'administer image styles',
      'generate textimage url derivatives',
    ));
    $this->drupalLogin($this->admin_user);

    // Change Textimage font directory.
    // @todo Form Ajax can not be tested at the moment, so going for direct
    // change to the config settings.
    $config = \Drupal::service('config.factory')->get('textimage.settings');
    $config->set('font.plugin_settings.textimage.path', drupal_get_path('module', 'textimage') . '/tests/fonts');
    $config->save();

    // Set default font.
    $this->drupalGet($this->textimageAdmin);
    $edit = array(
      'font[default_font_name]' => 'Old Standard TT Regular',
    );
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Create a test image style.
    $edit = array(
      'name' => 'textimage_test',
      'label' => 'Textimage Test',
    );
    $this->drupalPostForm('admin/config/media/image-styles/add', $edit, t('Create new style'));

    // Create a test textimage_text effect.
    $this->drupalPostForm('admin/config/media/image-styles/manage/textimage_test/add/textimage_text', array(), t('Add effect'));

    // Set image storage to 'public' wrapper. @todo
/*    $edit = array(
      'textimage_options[uri_scheme]' => 'public',
    );
    $this->drupalPostForm('admin/config/media/image-styles/manage/textimage_test', $edit, t('Update style'));*/

    // Generate a few derivative images via theme.
    $textimage = array();
    $textimage[0] = array(
      '#theme' => 'textimage_style_image',
      '#style_name' => 'textimage_test',
      '#text' => array('preview text image'),
    );
    $textimage[1] = array(
      '#theme' => 'textimage_style_image',
      '#style_name' => 'textimage_test',
      '#text' => array('Предварительный просмотр текста'),
    );
    $textimage[2] = array(
      '#theme' => 'textimage_style_image',
      '#style_name' => 'textimage_test',
      '#text' => array('προεπισκόπηση της εικόνας κείμενο'),
    );
    $output = drupal_render($textimage);

    // Check files were generated.
    $files_count = count(file_scan_directory($directory_path . '/textimage/textimage_test', '/.*/'));
    $this->assertTrue($files_count == 3, t('Textimage generation via theme.'));

    // Build and display a URL derivative. @todo does not work, simpletest failure (error 500)
/* @todo variable_set('clean_url', 1);*/
    $this->drupalGet($directory_path . '/textimage/textimage_test/url_preview_text_image');
    $this->assertResponse(200);

    // Check file was generated.
    $files_count = count(file_scan_directory($directory_path . '/textimage/textimage_test', '/.*/'));
    $this->assertTrue($files_count == 4, t('Textimage generation via request URL.'));

    // Build a textimage at target URI.
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
    $this->assertTrue($files_count == 1, t('Textimage generation at target URI.'));

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
    $this->assertTrue($files_count == 1, t('Textimage replaced at target URI.'));

  }

}
