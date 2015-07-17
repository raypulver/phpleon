<?php

namespace LEON;

include_once 'io.php';

class Channel {
  public $spec = NULL;
  function __construct ($spec) {
    $this->spec = $spec;
  }
  function encode ($payload) {
    $enc = new Encoder($payload, $this->spec);
    return $enc->writeData()->export();
  }
  function decode ($buffer) {
    $buffer = new StringBuffer($buffer);
    $parser = new Parser($buffer, $this->spec);
    return $parser->parseValueWithSpec();
  }
}

?>
