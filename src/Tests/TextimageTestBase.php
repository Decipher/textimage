<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\Component\Utility\String;
use Drupal\simpletest\WebTestBase;

/**
 * Base test class for Textimage tests.
 */
abstract class TextimageTestBase extends WebTestBase {

  protected $textimageAdmin = 'admin/config/media/textimage';
  protected $textimageFactory;

  public static $modules = array('textimage', 'node');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->textimageFactory = \Drupal::service('textimage.factory');

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }

    // Create a user and log it in.
    $this->admin_user = $this->drupalCreateUser(array(
      'access content',
      'create article content',
      'edit any article content',
      'delete any article content',
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
    $style_name = 'textimage_test';
    $style_label = 'Textimage Test';
    $style_path = 'admin/config/media/image-styles/manage/' . $style_name;
    $edit = array(
      'name' => $style_name,
      'label' => $style_label,
    );
    $this->drupalPostForm('admin/config/media/image-styles/add', $edit, t('Create new style'));
    $this->assertRaw(t('Style %name was created.', array('%name' => $style_label)));

    // Create a test textimage_text effect.
    $effect_edits = array(
      'textimage_text' => array(
        'data[text_default][text_string]' => 'Test preview',
      ),
    );
    foreach ($effect_edits as $effect => $edit) {
      // Add the effect.
      $this->drupalPostForm($style_path, array('new' => $effect), t('Add'));
      if (!empty($edit)) {
        $this->drupalPostForm(NULL, $edit, t('Add effect'));
      }
    }

    // Set image storage to 'public' wrapper. @todo
/*    $edit = array(
      'textimage_options[uri_scheme]' => 'public',
    );
    $this->drupalPostForm('admin/config/media/image-styles/manage/textimage_test', $edit, t('Update style'));*/

  }

  /**
   * Create a new field for Textimage formatter.
   *
   * @param $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param $type_name
   *   The node type that this field will be added to.
   * @param $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param $instance_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  protected function createTextimageField($name, $type_name, $storage_settings = array(), $instance_settings = array(), $widget_settings = array()) {
    $field = array(
      'name' => $name,
      'entity_type' => 'node',
      'type' => 'text',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    );
    entity_create('field_storage_config', $field)->save();

    $instance = array(
      'field_name' => $field['name'],
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $type_name,
      'required' => !empty($instance_settings['required']),
      'description' => !empty($instance_settings['description']) ? $instance_settings['description'] : '',
      'settings' => array(),
    );
    $instance['settings'] = array_merge($instance['settings'], $instance_settings);
    $field_instance_config = entity_create('field_instance_config', $instance);
    $field_instance_config->save();

    entity_get_form_display('node', $type_name, 'default')
      ->setComponent($field['name'], array(
        'type' => 'text_textfield',
        'settings' => $widget_settings,
      ))
      ->save();

    entity_get_display('node', $type_name, 'default')
      ->setComponent($field['name'])
      ->save();

    return $field_instance_config;

  }

  /**
   * Create a node.
   *
   * @param $field_name
   *   Name of the field formatted by Textimage.
   * @param $field_value
   *   Value of the field formatted by Textimage.
   * @param $type
   *   The type of node to create.
   */
  protected function createTextimageNode($field_name, $field_value, $type) {
    $edit = array(
      'title[0][value]' => $field_value,
      'body[0][value]' => $field_value,
      $field_name . '[0][value]' => $field_value,
    );
    $this->drupalPostForm('node/add/' . $type, $edit, t('Save'));

    // Retrieve ID of the newly created node from the current URL.
    $matches = array();
    preg_match('/node\/([0-9]+)/', $this->getUrl(), $matches);
    return isset($matches[1]) ? $matches[1] : FALSE;
  }

  /**
   * Asserts a Textimage.
   */
  protected function assertTextimage($path, $width, $height) {
    $image = \Drupal::service('image.factory')->get($path);
    $w_error = abs($image->getWidth() - $width);
    $h_error = abs($image->getHeight() - $height);
    $tolerance = 0.1;
    $this->assertTrue($w_error < $width * $tolerance && $h_error < $height * $tolerance, String::format('Textimage width and height (@act_wx@act_h) approximate expected results (@exp_wx@exp_h)', array('@act_w' => $image->getWidth(), '@act_h' => $image->getHeight(), '@exp_w' => $width, '@exp_h' => $height)));
  }

}
