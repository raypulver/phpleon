<?php

namespace LEON;

class RegExp {
  public $pattern = null;
  public $modifier = null;
  public function __construct() {
    $args = func_get_args();
    $this->pattern = $args[0];
    if (isset($args[1])) $this->modifier = $args[1];
    else $this->modifier = '';
  }
  public function toString() {
    return '/' . $this->pattern . '/' . $this->modifier;
  }
}
