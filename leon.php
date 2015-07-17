<?php

include_once 'io.php';
include_once 'channel.php';

define("LEON_UNSIGNED_CHAR", 0x00);
define("LEON_CHAR", 0x01);
define("LEON_UNSIGNED_SHORT", 0x02);
define("LEON_SHORT", 0x03);
define("LEON_UNSIGNED_INT", 0x04);
define("LEON_INT", 0x05);
define("LEON_FLOAT", 0x06);
define("LEON_DOUBLE", 0x07);
define("LEON_STRING", 0x10);
define("LEON_BOOLEAN", 0x20);
define("LEON_NULL", 0x40);
define("LEON_UNDEFINED", 0x14);
define("LEON_DATE", 0x15);
define("LEON_BUFFER", 0x16);
define("LEON_REGEXP", 0x17);
define("LEON_NAN", 0x18);

function leon_encode ($payload) {
  $enc = new LEON\Encoder($payload);
  return $enc->writeSI()->writeOLI()->writeData()->export();
}
function leon_decode ($buffer) {
  $buffer = new LEON\StringBuffer($buffer);
  $parser = new LEON\Parser($buffer);
  return $parser->parseSI()->parseOLI()->parseValue();
}
?>
