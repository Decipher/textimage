<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

use Drupal\node\Entity\Node;

/**
 * Test Textimage formatter on node display.
 *
 * @group Textimage
 */
class TextimageFieldFormatterTest extends TextimageTestBase {

  protected $dumpHeaders = TRUE;

  /**
   * Test Textimage formatter on node display.
   */
  function testTextimageFieldFormatter() {

    // Create a text field for Textimage test.
    $field_name = strtolower($this->randomMachineName());
    $this->createTextimageField($field_name, 'article');

    // Create a new node.
    $field_value = $this->randomMachineName(20);
    $nid = $this->createTextimageNode($field_name, $field_value, 'article');
    $node = Node::load($nid);

    // Get Textimage URL.
    $textimage_url = $this->textimageFactory->get()
      ->styleByName('textimage_test')
      ->node($node)
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
    $this->assertCacheTag('config:image.style.textimage_test');

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
    $this->assertCacheTag('config:image.style.textimage_test');

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
    $this->assertCacheTag('config:image.style.textimage_test');

  }

}
