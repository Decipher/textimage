<?php

namespace Drupal\textimage\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\simpletest\WebTestBase;

/**
 * Base test class for Textimage tests.
 */
abstract class TextimageTestBase extends WebTestBase {

  protected $textimageAdmin = 'admin/config/media/textimage';
  protected $textimageFactory;
  protected $renderer;

  public static $modules = ['textimage', 'node', 'image_effects', 'imagemagick'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->textimageFactory = $this->container->get('textimage.factory');
    $this->renderer = $this->container->get('renderer');

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }

    // Create a user and log it in.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit any article content',
      'delete any article content',
      'administer site configuration',
      'administer image styles',
      'generate textimage url derivatives',
    ]);
    $this->drupalLogin($this->adminUser);

    // Change Image Effects settings.
    $config = \Drupal::configFactory()->getEditable('image_effects.settings');
    $config
      ->set('image_selector.plugin_id', 'dropdown')
      ->set('image_selector.plugin_settings.dropdown.path', drupal_get_path('module', 'image_effects') . '/tests/images')
      ->set('font_selector.plugin_id', 'dropdown')
      ->set('font_selector.plugin_settings.dropdown.path', drupal_get_path('module', 'image_effects') . '/tests/fonts/LinLibertineTTF_5.3.0_2012_07_02')
      ->save();

    // Change Textimage settings.
    $config = \Drupal::configFactory()->getEditable('textimage.settings');
    $config
      ->set('url_generation.enabled', TRUE)
      ->set('debug', TRUE)
      ->save();

    // Set default font.
    $this->drupalGet($this->textimageAdmin);
    $edit = [
      'settings[main][default_font_uri]' => 'LinLibertine_Rah.ttf',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Create a test image style.
    $style_name = 'textimage_test';
    $style_label = 'Textimage Test';
    $style_path = 'admin/config/media/image-styles/manage/' . $style_name;
    $edit = [
      'name' => $style_name,
      'label' => $style_label,
    ];
    $this->drupalPostForm('admin/config/media/image-styles/add', $edit, t('Create new style'));
    $this->assertRaw(t('Style %name was created.', ['%name' => $style_label]));

    // Create a test image_effects_text_overlay effect.
    $effect_edits = [
      'image_effects_text_overlay' => [
        'data[text_default][text_string]' => 'Test preview',
      ],
    ];
    foreach ($effect_edits as $effect => $edit) {
      // Add the effect.
      $this->drupalPostForm($style_path, ['new' => $effect], t('Add'));
      if (!empty($edit)) {
        $this->drupalPostForm(NULL, $edit, t('Add effect'));
      }
    }
  }

  /**
   * Create a new field for Textimage formatter.
   *
   * @param string $type
   *   The type of the new field.
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $bundle
   *   The node type that this field will be added to.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of field settings that will be added to the field defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  protected function createTextimageField($type, $name, $bundle, $storage_settings = [], $field_settings = [], $widget_settings = []) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => $name,
      'label' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'required' => !empty($field_settings['required']),
      'description' => !empty($field_settings['description']) ? $field_settings['description'] : '',
      'settings' => $field_settings,
    ])->save();

    entity_get_form_display('node', $bundle, 'default')
      ->setComponent($name, [
        'type' => $type == 'text' ? 'text_textfield' : 'image_image',
        'settings' => $widget_settings,
      ])
      ->save();

    entity_get_display('node', $bundle, 'default')
      ->setComponent($name)
      ->save();

    return $field_config;

  }

  /**
   * Create a node.
   *
   * @param string $field_type
   *   Type of the field formatted by Textimage.
   * @param string $field_name
   *   Name of the field formatted by Textimage.
   * @param string $field_value
   *   Value of the field formatted by Textimage.
   * @param string $bundle
   *   The type of node to create.
   */
  protected function createTextimageNode($field_type, $field_name, $field_value, $bundle) {
    switch ($field_type) {
      case 'text':
        if (!is_array($field_value)) {
          $field_value = [$field_value];
        }
        $edit = [
          'title[0][value]' => $field_value[0],
          'body[0][value]' => $field_value[0],
        ];
        for ($i = 0; $i < count($field_value); $i++) {
          $index = $field_name . '[' . $i . '][value]';
          $edit[$index] = $field_value[$i];
        }
        $this->drupalPostForm('node/add/' . $bundle, $edit, t('Save'));
        break;

      case 'image':
        $edit = [
          'title[0][value]' => $this->randomMachineName(),
        ];
        $edit['files[' . $field_name . '_0]'] = drupal_realpath($field_value->uri);
        $this->drupalPostForm('node/add/' . $bundle, $edit, t('Save'));
        // Add alt text.
        $this->drupalPostForm(NULL, [$field_name . '[0][alt]' => 'test alt text'], t('Save'));
        break;

    }

    // Retrieve ID of the newly created node from the current URL.
    $matches = [];
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
    $this->assertTrue($w_error < $width * $tolerance && $h_error < $height * $tolerance, "Textimage {$path} width and height ({$image->getWidth()}x{$image->getHeight()}) approximate expected results ({$width}x{$height})");
  }

  /**
   * Returns the URI of a Textimage based on style name and text.
   */
  protected function getTextimageUriFromStyleAndText($style_name, $text) {
    return $this->textimageFactory->get()
      ->setStyle(ImageStyle::load($style_name))
      ->process($text)
      ->getUri();
  }

}
