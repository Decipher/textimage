<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;

/**
 * Test Textimage formatters on node display.
 *
 * @group Textimage
 */
class TextimageFieldFormatterTest extends TextimageTestBase {

  /**
   * Set headers to be displayed.
   */
  protected $dumpHeaders = TRUE;

  /**
   * Test Textimage formatter on node display and text field.
   */
  public function testTextimageTextFieldFormatter() {

    // Create a text field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $this->createTextimageField('text', $field_name, 'article');

    // Create a new node.
    $field_value = $this->randomMachineName(20);
    $nid = $this->createTextimageNode('text', $field_name, $field_value, 'article');
    $node = Node::load($nid);

    // Get Textimage URL.
    $textimage_url = $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTokenData(['node' => $node])
      ->process($field_value)
      ->getUrl()->toString();

    // Test the textimage formatter - no link.
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['type'] = 'textimage_text_field_formatter';
    $display_options['settings']['image_style'] = 'textimage_test';
    $display_options['settings']['image_link'] = '';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:title]';
    $display_options['settings']['image_title'] = 'Title: [node:title]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->cssSelect("img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Unlinked Textimage displaying on full node view.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $field_value);
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $field_value);

    // Test the textimage formatter - linked to content.
    $display_options['settings']['image_link'] = 'content';
    $display->setComponent($field_name, $display_options)
      ->save();
    $href = $node->urlInfo()->toString();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href*='$href'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to content displaying on full node view.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $field_value);
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $field_value);

    // Test the textimage formatter - linked to Textimage file.
    $display_options['settings']['image_link'] = 'file';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author]';
    $display_options['settings']['image_title'] = 'Title: [node:author]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href='$textimage_url'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to image file displaying on full node view.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $this->adminUser->getUsername());
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $this->adminUser->getUsername());

    // Check that alternate text and title tokens are resolved and their
    // cacheability metadata added.
    $site_name = \Drupal::configFactory()->get('system.site')->get('name');
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author] [site:name]';
    $display_options['settings']['image_title'] = 'Title: [node:author] [site:name]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href='$textimage_url'] img[src='$textimage_url']");
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $this->adminUser->getUsername() . ' ' . $site_name);
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $this->adminUser->getUsername() . ' ' . $site_name);
    $this->assertCacheTag('config:image.style.textimage_test');
    $this->assertCacheTag('config:system.site');

    // Check token.
    $bubbleable_metadata = new BubbleableMetadata();
    $token_resolved = \Drupal::service('token')->replace('[textimage:uri:' . $field_name . '] [site:name]', ['node' => $node], [], $bubbleable_metadata);
    $this->assertEqual($this->getTextimageUriFromStyleAndText('textimage_test', $field_value) . ' ' . $site_name, $token_resolved);
    $expected_tags = [
      'config:image.style.textimage_test',
      'config:system.site',
      'node:' . $node->id(),
    ];
    $this->assertEqual($expected_tags, array_intersect($expected_tags, $bubbleable_metadata->getCacheTags()), 'Token replace produced expected cache tags.');
  }

  /**
   * Test Textimage formatter on multi-value text fields.
   */
  public function testTextimageMultiValueTextFieldFormatter() {

    // Create a multi-value text field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $this->createTextimageField('text', $field_name, 'article', array('cardinality' => 4));

    // Create a new node, with 4 text values for the field.
    $field_value = array();
    for ($i = 0; $i < 4; $i++) {
      $field_value[] = $this->randomMachineName(20);
    }
    $nid = $this->createTextimageNode('text', $field_name, $field_value, 'article');
    $node = Node::load($nid);

    // Test the textimage formatter - one image.
    $textimage_url = $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTokenData(['node' => $node])
      ->process($field_value)
      ->getUrl()->toString();
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['type'] = 'textimage_text_field_formatter';
    $display_options['settings']['image_style'] = 'textimage_test';
    $display_options['settings']['image_text_values'] = 'merge';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:title]';
    $display_options['settings']['image_title'] = 'Title: [node:title]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->cssSelect("div.field--name-{$field_name} div.field__items img");
    $this->assertEqual(1, count($elements));
    $this->assertEqual($textimage_url, $elements[0]['src']->__toString());
    $this->assertEqual('Alternate text: ' . $field_value[0], $elements[0]['alt']->__toString());
    $this->assertEqual('Title: ' . $field_value[0], $elements[0]['title']->__toString());

    // Test the textimage formatter - multiple images.
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['settings']['image_text_values'] = 'itemize';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->cssSelect("div.field--name-{$field_name} div.field__items img");
    $this->assertEqual(4, count($elements));
    for ($i = 0; $i < 4; $i++) {
      $textimage_url = $this->textimageFactory->get()
        ->setStyle(ImageStyle::load('textimage_test'))
        ->setTokenData(['node' => $node])
        ->process($field_value[$i])
        ->getUrl()->toString();
      $this->assertEqual($textimage_url, $elements[$i]['src']->__toString());
      $this->assertEqual('Alternate text: ' . $field_value[0], $elements[$i]['alt']->__toString());
      $this->assertEqual('Title: ' . $field_value[0], $elements[$i]['title']->__toString());
    }
  }

  /**
   * Test Textimage formatter on image fields.
   */
  public function testTextimageImageFieldFormatter() {

    // Create an image field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $min_resolution = 50;
    $max_resolution = 100;
    $field_settings = array(
      'max_resolution' => $max_resolution . 'x' . $max_resolution,
      'min_resolution' => $min_resolution . 'x' . $min_resolution,
      'alt_field' => 1,
    );
    $this->createTextimageField('image', $field_name, 'article', [], $field_settings);

    // Create a new node.
    // Get image 'image-1.png'
    $field_value = $this->drupalGetTestFiles('image', 39325)[0];
    $nid = $this->createTextimageNode('image', $field_name, $field_value, 'article');
    $node = Node::load($nid);
    $node_title = $node->get('title')[0]->get('value')->getValue();

    // Get the stored image.
    $fid = $node->{$field_name}[0]->get('target_id')->getValue();
    $source_image_file = File::load($fid);
    $source_image_file_url = file_create_url($source_image_file->getFileUri());

    // Get Textimage URL.
    $textimage_url = $this->textimageFactory->get()
      ->setSourceImageFile($source_image_file)
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTokenData(['node' => $node, 'file' => $source_image_file])
      ->process(NULL)
      ->getUrl()->toString();

    // Test the textimage formatter - no link.
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['type'] = 'textimage_image_field_formatter';
    $display_options['settings']['image_style'] = 'textimage_test';
    $display_options['settings']['image_link'] = '';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:title]';
    $display_options['settings']['image_title'] = 'Title: [node:title]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->cssSelect("img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Unlinked Textimage displaying on full node view.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $node_title);
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $node_title);

    // Test the textimage formatter - linked to content. Also not providing
    // alt text on formatter leads to rendering the ImageItem alt text.
    $display_options['settings']['image_link'] = 'content';
    $display_options['settings']['image_alt'] = '';
    $display->setComponent($field_name, $display_options)
      ->save();
    $href = $node->urlInfo()->toString();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href*='$href'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to content displaying on full node view.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'test alt text');
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $node_title);

    // Test the textimage formatter - linked to original image.
    $display_options['settings']['image_link'] = 'file';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author]';
    $display_options['settings']['image_title'] = 'Title: [node:author]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href='$source_image_file_url'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to original image file.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $this->adminUser->getUsername());
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $this->adminUser->getUsername());

    // Test the textimage formatter - linked to derivative image.
    $display_options['settings']['image_link'] = 'derivative';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href='$textimage_url'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to derivative image file.');
    $this->assertEqual($elements[0]['alt']->__toString(), 'Alternate text: ' . $this->adminUser->getUsername());
    $this->assertEqual($elements[0]['title']->__toString(), 'Title: ' . $this->adminUser->getUsername());

    // Check that alternate text and title tokens are resolved and their
    // cacheability metadata added.
    $site_name = \Drupal::configFactory()->get('system.site')->get('name');
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author] [site:name]';
    $display_options['settings']['image_title'] = 'Title: [node:author] [site:name]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $this->assertCacheTag('config:image.style.textimage_test');
    $this->assertCacheTag('config:system.site');
    $this->assertCacheTag('node:' . $node->id());
    $this->assertCacheTag('file:' . $source_image_file->id());
    $this->assertCacheTag('user:' . $this->adminUser->id());

    // Check token.
    $bubbleable_metadata = new BubbleableMetadata();
    $token_resolved = \Drupal::service('token')->replace('[textimage:uri:' . $field_name . '] [site:name]', ['node' => $node], [], $bubbleable_metadata);
    $textimage = $this->textimageFactory->get()
      ->setSourceImageFile($source_image_file)
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTokenData(['node' => $node, 'file' => $source_image_file])
      ->process(NULL);
    $this->assertEqual($textimage->getUri() . ' ' . $site_name, $token_resolved);
    $expected_tags = [
      'config:image.style.textimage_test',
      'config:system.site',
      'node:' . $node->id(),
      'file:' . $source_image_file->id(),
    ];
    $this->assertEqual($expected_tags, array_intersect($expected_tags, $bubbleable_metadata->getCacheTags()), 'Token replace produced expected cache tags.');
  }
}
