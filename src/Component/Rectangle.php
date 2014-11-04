<?php

/**
 * @file
 * Contains \Drupal\textimage\Component\Rectangle.
 */

namespace Drupal\textimage\Component;

/**
 * Rectangle algebra class.
 */
class Rectangle {

  protected $points = [];
  protected $width = 0;
  protected $height = 0;

  protected $angle = 0;
  protected $rotationOffset = [0, 0];

  /**
   * Constructs a new Rectangle object.
   *
   * @param int $width
   *   (Optional) The width of the rectangle.
   * @param int $height
   *   (Optional) The height of the rectangle.
   */
  public function __construct($width = 0, $height = 0) {
    if ($width !== 0 && $height !== 0) {
      $this->setFromDimensions($width, $height);
    }
  }

  /**
   * @todo
   */
  public function setPoint($id, array $coords = [0, 0]) {
    $this->points[$id] = $coords;
    return $this;
  }

  /**
   * @todo
   */
  public function getPoint($id) {
    return $this->points[$id];
  }

  /**
   * @todo
   */
  public function setFromDimensions($width, $height) {
    $this->setFromCorners([
      'c_a' => [0, 0],
      'c_b' => [$width - 1, 0],
      'c_c' => [$width - 1, $height - 1],
      'c_d' => [0, $height - 1],
    ]);
    return $this;
  }

/*  public function setFromDimensions($width, $height, $angle = 0) {
    $this->width = $width;
    $this->height = $height;
    $this->setPoint('center');
    $diag = sqrt(pow($this->width, 2) + pow($this->height, 2));
    $radius = $diag / 2;
    $wd = $this->width / $diag;
    $a = acos($wd);
    $r = deg2rad($angle);
    $this->setPoint('c_a', [-cos($a - $r) * $radius, -sin($a - $r) * $radius]);
    $this->setPoint('c_b', [cos($a + $r) * $radius, -sin($a + $r) * $radius]);
    $this->setPoint('c_c', [cos($a - $r) * $radius, sin($a - $r) * $radius]);
    $this->setPoint('c_d', [-cos($a + $r) * $radius, sin($a + $r) * $radius]);
    $this->determineBoundingCorners();
    return $this;
  }*/

  /**
   * @todo
   */
  public function setFromCorners(array $corners) {
    $this
      ->setPoint('c_a', $corners['c_a'])
      ->setPoint('c_b', $corners['c_b'])
      ->setPoint('c_c', $corners['c_c'])
      ->setPoint('c_d', $corners['c_d'])
      ->determineBoundingCorners();
    $this->width = $this->getBoundingWidth();
    $this->height = $this->getBoundingHeight();
    return $this;
  }

  /**
   * @todo
   */
  public function getWidth() {
    return $this->width;
  }

  /**
   * @todo
   */
  public function getHeight() {
    return $this->height;
  }

  /**
   * @todo
   */
  public function getRotationOffset() {
    return $this->rotationOffset;
  }

  /**
   * @todo
   */
  public function getBoundingWidth() {
    return $this->points['o_c'][0] - $this->points['o_a'][0] + 1;
  }

  /**
   * @todo
   */
  public function getBoundingHeight() {
    return $this->points['o_c'][1] - $this->points['o_a'][1] + 1;
  }

  /**
   * @todo
   */
  protected function translatePoint(array &$point, array $offset) {
    $point[0] += $offset[0];
    $point[1] += $offset[1];
    return $this;
  }

  /**
   * Translate a point, by an offset and a rotation angle.
   *
   * @param int $x
   *   x coordinate of the point
   * @param int $y
   *   y coordinate of the point
   * @param float $angle
   *   rotation angle
   * @param array $offset
   *   offset array (x, y)
   *
   * @return array
   *   array with x,y translated coordinates
   */
  protected function rotatePoint(&$point, $angle, $offset) {
    $rad = deg2rad($angle);
    $sin = sin($rad);
    $cos = cos($rad);
    list($x, $y) = $point;
    $point[0] = round($x * $cos + $y * -$sin) + $offset[0];
    $point[1] = round($y * $cos - $x * -$sin) + $offset[1];
    return $this;
  }

  /**
   * Get a rotated box object.
   *
   * @param float $angle
   *   rotation angle
   * @param array $offset
   *   offset requested before rotation
   * @param array $rotation_offset
   *   position of top left corner for rotation
   */
  public function getTranslatedRectangle($angle, $offset = NULL, $rotation_offset = NULL) {
    $output = clone $this;
    if ($offset) {
      $output->translateAllPoints($offset);
    }
    if ($angle) {
      $output->angle = $angle;
      $output->rotateAllPoints($angle, $rotation_offset);
      if (!$rotation_offset) {
        $output->determineBoundingCorners();
        $output->rotationOffset = [-$output->points['o_a'][0], -$output->points['o_a'][1]];
        $output->translateAllPoints($output->rotationOffset);
      }
    }
    return $output;
  }

  /**
   * Rotate the rectangle and any additional point.
   *
   * @param float $angle
   *   Rotation angle.
   * @param array $rotation_offset
   *   Translation offset array (x, y) coming from previous rotation.
   */
  protected function rotateAllPoints($angle, $rotation_offset) {
    foreach ($this->points as &$point) {
      $this->rotatePoint($point, $angle, $rotation_offset);
    }
  }

  /**
   * Translate the box by an offset.
   *
   * @param array $offset
   *   offset array (x, y)
   */
  protected function translateAllPoints($offset) {
    foreach ($this->points as &$point) {
      $this->translatePoint($point, $offset);
    }
  }

  /**
   * @todo
   */
  protected function determineBoundingCorners() {
    $this
      ->setPoint(
        'o_a', [
          min($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]),
          min($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1])
        ]
      )
      ->setPoint(
        'o_c', [
          max($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]),
          max($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1])
        ]
      );
    return $this;
  }
}
