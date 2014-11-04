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

  public function setPoint($id, array $coords = [0, 0]) {
    $this->points[$id] = $coords;
    return $this;
  }

  public function getPoint($id) {
    return $this->points[$id];
  }

  public function setPointX($id, $x) {
    $this->points[$id][0] = $x;
    return $this;
  }

  public function setPointY($id, $y) {
    $this->points[$id][1] = $y;
    return $this;
  }

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
    $this->setPointX('o_a', min($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]));
    $this->setPointY('o_a', min($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]));
    $this->setPointX('o_c', max($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]));
    $this->setPointY('o_c', max($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]));
    return $this;
  }*/

  public function setFromCorners(array $corners) {
    $this
      ->setPoint('c_a', $corners['c_a'])
      ->setPoint('c_b', $corners['c_b'])
      ->setPoint('c_c', $corners['c_c'])
      ->setPoint('c_d', $corners['c_d']);
    $this->setPoint('basepoint');
    $this->setPoint('topLeftCornerPosition');
    $this->setPointX('o_a', min($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]));
    $this->setPointY('o_a', min($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]));
    $this->setPointX('o_c', max($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]));
    $this->setPointY('o_c', max($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]));
    $this->width = $this->getBoundingWidth();
    $this->height = $this->getBoundingHeight();
    return $this;
  }

  public function getWidth() {
    return $this->width;
  }

  public function getHeight() {
    return $this->height;
  }

  public function getBoundingWidth() {
    return $this->points['o_c'][0] - $this->points['o_a'][0] + 1; // @todo +1 to be checked
  }

  public function getBoundingHeight() {
    return $this->points['o_c'][1] - $this->points['o_a'][1] + 1; // @todo +1 to be checked
  }

  public function layOnAxes() { // @todo define the corner
    $x_offset = -$this->points['c_a'][0];
    $y_offset = -$this->points['c_a'][1];
    foreach ($this->points as &$point) {
      $this->translatePoint($point, $x_offset, $y_offset);
    }
    return $this;
  }

  protected function translatePoint(&$point, $x_offset, $y_offset) {
    $point[0] += $x_offset;
    $point[1] += $y_offset;
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
  protected function rotatePoint(&$point, $angle, $x_offset = 0, $y_offset = 0) {
    $rad = deg2rad($angle);
    $sin = sin($rad);
    $cos = cos($rad);
    list($x, $y) = $point;
    $point[0] = round($x * $cos + $y * -$sin) + $x_offset;
    $point[1] = round($y * $cos - $x * -$sin) + $y_offset;
    return $this;
  }

  public function getCorners() {
    $msg = [];
//    foreach (array('c_a', 'c_b', 'c_c', 'c_d') as $c) {
    foreach (array('c_d', 'c_c', 'c_b', 'c_a') as $c) {
      $msg[] = $this->points[$c][0];
      $msg[] = $this->points[$c][1];
    }
    return $msg;
  }

  /**
   * Get a rotated box object.
   *
   * @param float $angle
   *   rotation angle
   * @param array $offset
   *   offset requested before rotation
   * @param array $top_left_corner_position
   *   position of top left corner for rotation
   */
  public function getTranslatedRectangle($angle, $offset = NULL, $top_left_corner_position = NULL) {
    $output = clone $this;
    if ($offset) {
      $output->translateAllPoints($offset);
    }
    if ($angle) {
      $output->rotateAllPoints($angle, $top_left_corner_position);
    }
    return $output;
  }

  /**
   * Rotate the box.
   *
   * @param float $angle
   *   rotation angle
   * @param array $top_left_corner_position
   *   box translation offset array (x, y) to reposition the box
   */
  protected function rotateAllPoints($angle, $top_left_corner_position) {
    foreach ($this->points as &$point) {
      $this->rotatePoint($point, $angle, $top_left_corner_position[0], $top_left_corner_position[1]);
    }
    if (!$top_left_corner_position) {
      $this->setPointX('o_a', min($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]));
      $this->setPointY('o_a', min($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]));
      $this->setPointX('o_c', max($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]));
      $this->setPointY('o_c', max($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]));
      $this->width = $this->points['o_c'][0] - $this->points['o_a'][0] + 1;
      $this->height = $this->points['o_c'][1] - $this->points['o_a'][1] + 1;
      $x_offset = -$this->points['o_a'][0];
      $y_offset = -$this->points['o_a'][1];
      $this->translateAllPoints([$x_offset, $y_offset]);
      $this->setPoint('topLeftCornerPosition', [$x_offset, $y_offset]);
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
      $this->translatePoint($point, $offset[0], $offset[1]);
    }
  }

}
