<?php

namespace Drupal\Tests\textimage\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Textimage theme functions.
 *
 * @group Textimage
 */
class TextimageThemeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'textimage',
    'image',
    'image_effects',
    'user',
    'system',
  ];

  /**
   * Test the Textimage formatter.
   */
  public function testTextimageFormatterTheme() {
    // Install the default image styles.
    $this->installConfig(['image', 'textimage']);

    $textimageFactory = $this->container->get('textimage.factory');
    $renderer = $this->container->get('renderer');

    $textimage = $textimageFactory->get();
    $textimage
      ->setStyle(ImageStyle::load('medium'))
      ->process(['one', 'two'])
      ->buildImage();

    // Test output of theme textimage_formatter.
    $output = [
      '#theme' => 'textimage_formatter',
      '#uri' => $textimage->getUri(),
      '#width' => $textimage->getWidth(),
      '#height' => $textimage->getHeight(),
      '#alt' => 'Alternate text',
      '#title' => 'Textimage title',
      '#attributes' => ['class' => 'textimage-test'],
      '#image_container_attributes' => ['class' => ['textimage-container-test']],
      '#anchor_url' => $textimage->getUrl(),
    ];
    $this->setRawContent($renderer->renderRoot($output));
    $abs_url = $textimage->getUrl()->toString();
    $rel_url = file_url_transform_relative($abs_url);
    // @todo changing behaviour in D8.1, need to watch #2646744
    $elements = $this->cssSelect("a[href='$abs_url'] div.textimage-container-test img[src='$rel_url']");
    $this->assertNotEmpty($elements, 'Textimage formatted correctly.');
  }

}
