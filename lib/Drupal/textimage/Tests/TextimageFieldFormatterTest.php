<?php

/**
 * @file
 * Textimage test case script.
 */

namespace Drupal\textimage\Tests;

/**
 * Test Textimage formatter on node display.
 */
class TextimageFieldFormatterTest extends TextimageTestBase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Textimage field formatter',
      'description' => 'Test Textimage display formatter',
      'group' => 'Textimage',
    );
  }

  /**
   * Test Textimage formatter on node display.
   */
  function testTextimageFieldFormatter() {

    // Create a text field for Textimage test.
    $field_name = strtolower($this->randomName());
    $this->createTextimageField($field_name, 'article');

    // Create a new node.
    $field_value = $this->randomName(20);
    $nid = $this->createTextimageNode($field_name, $field_value, 'article');
    $node = node_load($nid, TRUE);

    // Get Textimage URL.
    $textimage_url = $this->textimageFactory->getImageUrl(
      'textimage_test',
      NULL,
      array($field_value),
      'png',
      TRUE,
      $node
    );

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
    $elements = $this->xpath(
      '//img[@src = :src]',
      array(
        ':src' => $textimage_url,
      )
    );
    $this->assertTrue(!empty($elements), 'Unlinked Textimage displaying on full node view.');
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $field_value, 'Textimage has expected alt attribute.');
    $this->assertEqual($elements[0]['title'], 'Title: ' . $field_value, 'Textimage has expected title attribute.');

    // Test the textimage formatter - linked to content.
    $display_options['settings']['image_link'] = 'content';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->xpath(
      '//a[contains(@href, :href)]/img[@src = :src]',
      array(
        ':href' => 'node/' . $nid,
        ':src' => $textimage_url,
      )
    );
    $this->assertTrue(!empty($elements), 'Textimage linked to content displaying on full node view.');
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $field_value, 'Textimage has expected alt attribute.');
    $this->assertEqual($elements[0]['title'], 'Title: ' . $field_value, 'Textimage has expected title attribute.');

    // Test the textimage formatter - linked to Textimage file.
    $display_options['settings']['image_link'] = 'file';
    $display_options['settings']['image_alt'] = 'Alternate text: [node:author]';
    $display_options['settings']['image_title'] = 'Title: [node:author]';
    $display->setComponent($field_name, $display_options)
      ->save();
    $this->drupalGet('node/' . $nid);
    $elements = $this->xpath(
      '//a[@href = :href]/img[@src = :src]',
      array(
        ':href' => $textimage_url,
        ':src' => $textimage_url,
      )
    );
    $this->assertTrue(!empty($elements), 'Textimage linked to image file displaying on full node view.');
    $this->assertEqual($elements[0]['alt'], 'Alternate text: ' . $this->admin_user->getUsername(), 'Textimage has expected alt attribute.');
    $this->assertEqual($elements[0]['title'], 'Title: ' . $this->admin_user->getUsername(), 'Textimage has expected title attribute.');

  }

}
