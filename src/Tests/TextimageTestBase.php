<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simpletest\WebTestBase;

/**
 * Base test class for Textimage tests.
 */
abstract class TextimageTestBase extends WebTestBase {

  protected $textimageAdmin = 'admin/config/media/textimage';
  protected $textimageFactory;
  protected $renderer;

  public static $modules = array('textimage', 'node');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->textimageFactory = $this->container->get('textimage.factory');
    $this->renderer = $this->container->get('renderer');

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'page', 'name' => 'Basic page'));
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }

    // Create a user and log it in.
    $this->adminUser = $this->drupalCreateUser(array(
      'access content',
      'create article content',
      'edit any article content',
      'delete any article content',
      'administer site configuration',
      'administer image styles',
      'generate textimage url derivatives',
    ));
    $this->drupalLogin($this->adminUser);

    // Change Textimage font directory.
    $config = \Drupal::configFactory()->getEditable('textimage.settings');
    $config
      ->set('font.plugin_settings.textimage.path', drupal_get_path('module', 'textimage') . '/tests/fonts')
      ->set('debug', TRUE)
      ->save();

    // Set default font.
    $this->drupalGet($this->textimageAdmin);
    $edit = array(
      'settings[font][default_font_name]' => 'Old Standard TT Regular',
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

    // Set image storage to 'public' wrapper.
    $edit = array(
      'textimage_options[uri_scheme]' => 'public',
    );
    $this->drupalPostForm('admin/config/media/image-styles/manage/textimage_test', $edit, t('Update style'));

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
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => 'text',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $type_name,
      'required' => !empty($instance_settings['required']),
      'description' => !empty($instance_settings['description']) ? $instance_settings['description'] : '',
      'settings' => $instance_settings,
    ])->save();

    entity_get_form_display('node', $type_name, 'default')
      ->setComponent($name, array(
        'type' => 'text_textfield',
        'settings' => $widget_settings,
      ))
      ->save();

    entity_get_display('node', $type_name, 'default')
      ->setComponent($name)
      ->save();

    return $field_config;

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
    $this->assertTrue($w_error < $width * $tolerance && $h_error < $height * $tolerance, SafeMarkup::format('Textimage width and height (@act_wx@act_h) approximate expected results (@exp_wx@exp_h)', array('@act_w' => $image->getWidth(), '@act_h' => $image->getHeight(), '@exp_w' => $width, '@exp_h' => $height)));
  }

}
