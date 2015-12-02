<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;

/**
 * Test Textimage formatter on node display.
 *
 * @group Textimage
 */
class TextimageFieldFormatterTest extends TextimageTestBase {

  /**
   * Set headers to be displayed.
   */
  protected $dumpHeaders = TRUE;

  /**
   * Test Textimage formatter on node display.
   */
  public function testTextimageFieldFormatter() {

    // Create a text field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $this->createTextimageField($field_name, 'article');

    // Create a new node.
    $field_value = $this->randomMachineName(20);
    $nid = $this->createTextimageNode($field_name, $field_value, 'article');
    $node = Node::load($nid);

    // Get Textimage URL.
    $textimage_url = $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTokenData(['node' => $node])
      ->process($field_value)
      ->getUrl();

    // Test the textimage formatter - no link.
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['type'] = 'textimage';
    $display_options['settings']['image_style'] = 'textimage_test';
    $display_options['settings']['image_link'] = '';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:title]';
    $display_options['settings']['image_title'] = 'Title: [node:title]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->cssSelect("img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Unlinked Textimage displaying on full node view.');
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $field_value);
    $this->assertEqual($elements[0]['title'], 'Title: ' . $field_value);

    // Test the textimage formatter - linked to content.
    $display_options['settings']['image_link'] = 'content';
    $display->setComponent($field_name, $display_options)
      ->save();
    $href = $node->urlInfo()->toString();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href*='$href'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to content displaying on full node view.');
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $field_value);
    $this->assertEqual($elements[0]['title'], 'Title: ' . $field_value);

    // Test the textimage formatter - linked to Textimage file.
    $display_options['settings']['image_link'] = 'file';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author]';
    $display_options['settings']['image_title'] = 'Title: [node:author]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href='$textimage_url'] img[src='$textimage_url']");
    $this->assertTrue(!empty($elements), 'Textimage linked to image file displaying on full node view.');
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $this->adminUser->getUsername());
    $this->assertEqual($elements[0]['title'], 'Title: ' . $this->adminUser->getUsername());

    // Check that alternate text and title tokens are resolved and their
    // cacheability metadata added.
    $site_name = \Drupal::configFactory()->get('system.site')->get('name');
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author] [site:name]';
    $display_options['settings']['image_title'] = 'Title: [node:author] [site:name]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet($node->urlInfo());
    $elements = $this->cssSelect("a[href='$textimage_url'] img[src='$textimage_url']");
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $this->adminUser->getUsername() . ' ' . $site_name);
    $this->assertEqual($elements[0]['title'], 'Title: ' . $this->adminUser->getUsername() . ' ' . $site_name);
    $this->assertCacheTag('config:image.style.textimage_test');
    $this->assertCacheTag('config:system.site');
  }

  /**
   * Test Textimage formatter on multi-value text fields.
   */
  public function testTextimageMultiValueFieldFormatter() {

    // Create a multi-value text field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $this->createTextimageField($field_name, 'article', array('cardinality' => 4));

    // Create a new node, with 4 text values for the field.
    $field_value = array();
    for ($i = 0; $i < 4; $i++) {
      $field_value[] = $this->randomMachineName(20);
    }
    $nid = $this->createTextimageNode($field_name, $field_value, 'article');
    $node = Node::load($nid);

    // Test the textimage formatter - one image.
    $textimage_url = $this->textimageFactory->get()
      ->setStyle(ImageStyle::load('textimage_test'))
      ->setTokenData(['node' => $node])
      ->process($field_value)
      ->getUrl();
    $display = entity_get_display('node', $node->getType(), 'default');
    $display_options['type'] = 'textimage';
    $display_options['settings']['image_style'] = 'textimage_test';
    $display_options['settings']['image_text_values'] = 'merge';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:title]';
    $display_options['settings']['image_title'] = 'Title: [node:title]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->cssSelect("div.field--name-{$field_name} div.field__items img");
    $this->assertEqual(1, count($elements));
    $this->assertEqual($textimage_url, $elements[0]['src'], 'Textimage has expected URL.');
    $this->assertEqual('Alternate text: ' . $field_value[0], $elements[0]['alt'], 'Textimage has expected alt attribute.');
    $this->assertEqual('Title: ' . $field_value[0], $elements[0]['title'], 'Textimage has expected title attribute.');

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
        ->getUrl();
      $this->assertEqual($textimage_url, $elements[$i]['src'], 'Textimage has expected URL.');
      $this->assertEqual('Alternate text: ' . $field_value[0], $elements[$i]['alt'], 'Textimage has expected alt attribute.');
      $this->assertEqual('Title: ' . $field_value[0], $elements[$i]['title'], 'Textimage has expected title attribute.');
    }
  }
}
