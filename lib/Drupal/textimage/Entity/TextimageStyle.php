<?php

/**
 * @file
 * Contains \Drupal\textimage\Entity\TextimageStyle.
 */

namespace Drupal\textimage\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Annotation\Translation;
use Drupal\image\ImageEffectBag;

/**
 * Defines an image style configuration entity.
 *
 * @EntityType(
 *   id = "textimage_style",
 *   label = @Translation("Textimage style"),
 *   module = "textimage",
 *   controllers = {
 *     "storage" = "Drupal\Core\Config\Entity\ConfigStorageController",
 *     "access" = "Drupal\image\ImageStyleAccessController"
 *   },
 *   config_prefix = "textimage.style",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class TextimageStyle extends ConfigEntityBase {

  /**
   * The image style used by this TextimageStyle.
   *
   * @var \Drupal\image\Entity\ImageStyleInterface
   */
  protected $imageStyle;

  /**
   * Constructs a TextimageStyle entity object.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    if (isset($values['id'])) {
      $this->imageStyle = entity_load('image_style', $values['id']);
    }
    else {
      $this->imageStyle = entity_create('image_style', array());
    }
  }

  /**
   * Check if an image style is Textimage relevant.
   *
   * Also, it loads Textimage properties to the 'textimage' key of the
   * style array.
   *
   * @param array $style
   *   the image style to check
   *
   * @return bool
   *   TRUE if style is Textimage relevant, otherwise FALSE
   */
  public function isTextimage() {
    if (!$style_effects = $this->getEffects()) {
      return FALSE;
    }
    foreach ($style_effects as $effect) {
      if (strpos($effect->getPluginId(), 'textimage') === 0) {  // @todo use provider
/* @todo when this is settable....       if (!is_array($style['textimage'])) {
          $style['textimage'] = empty($style['textimage']) ? array() : (array) (unserialize($style['textimage']));
        }
        $style['textimage'] += static::defaultSettings();*/
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the image style used by this TextimageStyle.
   *
   * @return \Drupal\image\Entity\ImageStyleInterface
   *   an image style
   */
  public function getImageStyle() {
    return $this->imageStyle;
  }

  /**
   * Updates the image style from an array of effects' configuration.
   *
   * This is used to enable the direct creation of a Textimage using core
   * image module functions and API. The runtime style object does not get
   * saved to db. It is used to be passed to ImageStyle::createDerivative()
   * to build a picture.
   *
   * @param array $effects_outline
   *   an array of image effects
   *
   * @return @todo
   *   image style
   */
  public function buildFromEffectsOutline($effects_outline) {
    $effect_bag = $this->imageStyle->getEffects();
    // Update the bag with the changes occurred to the effects.
    foreach ($effects_outline as $e) {
      $effect_bag->updateConfiguration($e);
    }
    $effect_bag->sort();
    // Rescan bag and merge with effects' defaults.
    foreach ($effect_bag->getConfiguration() as $instance_id => $effect_config) {
      $default_config = $effect_bag->get($instance_id)->defaultConfiguration();
      $effect_config['data'] = array_replace_recursive($default_config, $effect_config['data']);
      $effect_bag->updateConfiguration($effect_config);
    }
    return $this;
  }

}
