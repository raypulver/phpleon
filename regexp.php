<?php

namespace LEON;

class RegExp {
  private $value = null;
  public function __construct($v) {
    $this->value = $v;
  }
  public function toString() {
    return $this->value;
  }
}
