<?php

namespace LEON;

include_once 'io.php';

function complement($v, $bits) {
  return ($v ^ ((1 << $bits) - 1)) + 1;
}
function bytes($v, $type) {
  $ret = array();
  if ($type === CHAR) {
    $ret[] = $v;
    return $ret;
  } else if ($type === (SIGNED | CHAR)) {
    return ($v < 0 ? bytes(complement(-val, 8), CHAR) : bytes(val, CHAR));
  } else if ($type === SHORT) {
    $ret[] = $v >> 8;
    $ret[] = $v & 0xFF;
    return $ret;
  } else if ($type === (SIGNED | SHORT)) {
    return ($v < 0 ? bytes(complement(-val, 16), SHORT) : bytes(val, SHORT));
  } else if ($type === INTV) {
    $ret[] = $v >> 24;
    $ret[] = ($v >> 16) & 0xFF;
    $ret[] = ($v >> 8) & 0xFF;
    $ret[] = $v & 0xFF;
    return $ret;
  } else if ($type === (SIGNED | INTV)) {
    return ($v < 0 ? bytes(complement(-val, 32), INTV) : bytes(val, INTV));
  } else if ($type === FLOATV) {
    $exp = 127;
    if ($v < 0) $sign = 1;
    else $sign = 0;
    $v = abs($v);
    $log = log($v)/log(2);
    if ($log < 0) $log = ceil($log);
    else $log = floor($log);
    $v *= pow(2, -$log + 23);
    $exp += $log;
    $v = round($v);
    $v &= 0x7FFFFF;
    $ret[] = $sign << 7;
    $ret[0] |= (($exp & 0xFE) >> 1);
    $ret[] = (($exp & 0x01) << 7);
    $ret[1] |= (($v >> 16) & 0x7F);
    $ret[] = (($v >> 8) & 0xFF);
    $ret[] = $v & 0xFF;
    return $ret;
  } else if ($type === DOUBLEV) {
    $exp = 1023;
    if ($v < 0) $sign = 1;
    else $sign = 0;
    $v = abs($v);
    $log = log($v)/log(2);
    if ($log < 0) $log = ceil($log);
    else $log = floor($log);
    $v *= pow(2, -log + 52);
    $exp += $log;
    $v = round($v);
    $v = bindec(substr(decbin($v), 1));
    $ret[] = $sign << 7;
    $ret[0] |= $exp >> 4;
    $ret[] = ($exp & 0x0F) << 4;
    $ret[1] |= floor($v*pow(2, -48)) & 0x0F;
    $sh = 40;
    for ($i = 0; $i < 6; ++$i) {
      $ret[] = floor($v*pow(2, -$sh)) & 0xFF;
    }
    return $ret;
  }
}
function bytes_to_float($bytes) {
  $sign = (0x80 & $bytes[0]) >> 7;
  $exp = (($bytes[0] & 0x7F) << 1) | (($bytes[1] & 0x80) >> 7);
  $sig = 0;
  $bytes[1] &= 0x7F;
  for ($i = 0; $i <= 2; ++$i) {
    $sig |= $bytes[$i + 1]*pow(2, (2 - $i)*8);
  }
  $sig |= 0x800000;
  return ($sign === 1 ? -$sig : $sig)*pow(2, $exp - (127 + 23));
}
function bytes_to_double($bytes) {
  $sign = (0x80 & $bytes[0]) >> 7;
  $exp = (($bytes[0] & 0x7F) << 4) | (($bytes[1] & 0xF0) >> 4);
  $sig = 0;
  $bytes[1] &= 0x0F;
  for ($i = 0; $i <= 6; ++$i) {
    $sig += $bytes[$i + 1]*pow(2, (6 - $i)*8);
  }
  $sig += 0x10000000000000;
  return ($sign === 1 ? -$sig : $sig)*pow(2, $exp - (1023 + 52));
}
class StringBuffer {
  public $buffer = "";
  function __construct() {
    $args = func_get_args();
    if (isset($args[0])) $this->buffer = $args[0];
  }
  static function concat($arr) {
    $accum = "";
    foreach ($arr as $v) {
      $accum .= $v->buffer;
    }
    return new self($accum);
  }
  function writeUInt8($v, $i) {
    $v = intval($v);
    if ($i === -1 || $i >= strlen($this->buffer)) {
      $this->buffer .= chr($v);
    } else {
      $this->buffer = substr($this->buffer, 0, $i) . chr($v) . substr($this->buffer, $i + 1);
    }
    return $i + 1;
  }
  function writeInt8($v, $i) {
    $v  = intval($v);
    $v = ($v < 0 ? complement(-$v, 8) : $v);
    return $this->writeUInt8($v, $i);
  }
  function writeUInt16LE($v, $i) {
    $v = intval($v);
    $bytes = bytes($v, SHORT);
    $add = "";
    foreach (array_reverse($bytes) as $b) {
      $add .= chr($b);
    }
    if ($i >= strlen($this->buffer) || $i === -1) {
      $this->buffer .= $add;
    } else {
      $this->buffer = substr($this->buffer, 0, $i) . $add . substr($this->buffer, $i + 2);
    }
    return $i + 2;
  }
  function writeUInt16BE($v, $i) {
    $v = intval($v);
    $bytes = bytes($v, SHORT);
    $add = "";
    foreach ($bytes as $b) {
      $add .= chr($b);
    }
    if ($i >= strlen($this->buffer) || $i === -1) {
      $this->buffer .= $add;
    } else {
      $this->buffer = substr($this->buffer, 0, $i) . $add . substr($this->buffer, $i + 2);
    }
    return $i + 2;
  }
  function writeInt16LE($v, $i) {
    $v = intval($v);
    $v = ($v < 0 ? complement(-$v, 16) : $v);
    return $this->writeUInt16LE($v, $i);
  }
  function writeInt16BE($v, $i) {
    $v = intval($v);
    $v = ($v < 0 ? complement(-$v, 16) : $v);
    return $this->writeUInt16BE($v, $i);
  }
  function writeUInt32LE($v, $i) {
    $v = intval($v);
    $bytes = bytes($v, INTV);
    $add = "";
    foreach (array_reverse($bytes) as $b) {
      $add .= chr($b);
    }
    if ($i >= strlen($this->buffer) || $i === -1) {
      $this->buffer .= $add;
    } else {
      $this->buffer = substr($this->buffer, 0, $i) . $add . substr($this->buffer, $i + 4);
    }
    return $i + 4;
  }
  function writeUInt32BE($v, $i) {
    $v = intval($v);
    $bytes = bytes($v, INTV);
    $add = "";
    foreach ($bytes as $b) {
      $add .= chr($b);
    }
    if ($i >= strlen($this->buffer) || $i === -1) {
      $this->buffer .= $add;
    } else {
      $this->buffer = substr($this->buffer, 0, $i) . $add . substr($this->buffer, $i + 4);
    }
    return $i + 4;
  }
  function writeInt32LE($v, $i) {
    $v = intval($v);
    $v = ($v < 0 ? complement(-$v, $i) : $v);
    return $this->writeUInt32LE($v, $i);
  }
  function writeInt32BE($v, $i) {
    $v = intval($v);
    $v = ($v < 0 ? complement(-$v, $i) : $v);
    return $this->writeUInt32BE($v, $i);
  }
  function writeFloatLE($v, $i) {
    $bytes = bytes($v, FLOATV);
    foreach (array_reverse($bytes) as $idx => $b) {
      $this->writeUInt8($b, $i + $idx);
    }
    return $i + 4;
  }
  function writeFloatBE($v, $i) {
    $bytes = bytes($v, FLOATV);
    foreach ($bytes as $idx => $b) {
      $this->writeUInt8($b, $i + $idx);
    }
    return $i + 4;
  }
  function writeDoubleLE($v, $i) {
    $bytes = bytes($v, DOUBLEV);
    foreach (array_reverse($bytes) as $idx => $b) {
      $this->writeUInt8($b, $i + $idx);
    }
    return $i + 8;
  }
  function writeDoubleBE($v, $i) {
    $bytes = bytes($v, DOUBLEV);
    foreach ($bytes as $idx => $b) {
      $this->writeUInt8($b, $i + $idx);
    }
    return $i + 8;
  }
  function readUInt8($i) {
    return ord($this->buffer[$i]);
  }
  function readInt8($i) {
    $v = ord($this->buffer[$i]);
    if ((0x80 & $v) !== 0) { return -complement($v, 8); }
    return $v;
  }
  function readUInt16LE($i) {
    return ord($this->buffer[$i]) | (ord($this->buffer[$i + 1]) << 8);
  }
  function readUInt16BE($i) {
    return (ord($this->buffer[$i]) << 8) | ord($this->buffer[$i + 1]);
  }
  function readInt16LE($i) {
    $v = $this->readUInt16LE($i);
    if (($v & 0x8000) !== 0) { return -complement($v, 16); }
    return $v;
  }
  function readInt16BE($i) {
    $v = $this->readUInt16BE($i);
    if (($v & 0x8000) !== 0) { return -complement($v, 16); }
    return $v;
  }
  function readUInt32LE($i) {
    return ord($this->buffer[$i]) | (ord($this->buffer[$i + 1]) << 8) | (ord($this->buffer[$i + 2]) << 16) | (ord($this->buffer[$i + 3]) << 24);
  }
  function readUInt32BE($i) {
    return (ord($this->buffer[$i]) << 24) | (ord($this->buffer[$i + 1]) << 16) | (ord($this->buffer[$i + 2]) << 8) | ord($this->buffer[$i + 3]);
  }
  function readInt32LE($i) {
    $v = $this->readUInt32LE($i);
    if (($v & 0x80000000) !== 0) {
      return -complement($v, 32);
    }
    return $v;
  }
  function readInt32BE($i) {
    $v = $this->readUInt32BE($i);
    if (($v & 0x80000000) !== 0) {
      return -complement($v, 32);
    }
    return $v;
  }
  function readFloatLE($i) {
    $bytes = array();
    for ($j = 0; $j < 4; ++$j) {
      $bytes[] = $this->readUInt8($i + $j);
    }
    return bytes_to_float(array_reverse($bytes));
  }
  function readFloatBE($i) {
    $bytes = array();
    for ($j = 0; $j < 4; ++$j) {
      $bytes[] = $this->readUInt8($i + $j);
    }
    return bytes_to_float($bytes);
  }
  function readDoubleLE($i) {
    $bytes = array();
    for ($j = 0; $j < 8; ++$j) {
      $bytes[] = $this->readUInt8($i + $j);
    }
    return bytes_to_double(array_reverse($bytes));
  }
  function readDoubleBE($i) {
    $bytes = array();
    for ($j = 0; $j < 8; ++$j) {
      $bytes[] = $this->readUInt8($i + $j);
    }
    return bytes_to_double($bytes);
  }
  function fill() {
    $args = func_get_args();
    $val = $args[0];
    if (!isset($args[1])) $offset = 0;
    else $offset = $args[1];
    if (!isset($args[2])) $end = strlen($this->buffer);
    $addendum = "";
    for ($i = $offset; $i < $end; ++$i) {
      $addendum .= chr($val);
    }
    $this->buffer = substr($this->buffer, 0, $offset) . $addendum . substr($this->buffer, $end);
    return $this;
  }
  function slice() {
    $args = func_get_args();
    if (!isset($args[0])) $start = 0;
    else $start = $args[0];
    if (!isset($args[1])) $end = strlen($this->buffer);
    $ret = new self();
    $ret->buffer = substr($this->buffer, $start, $end - $start);
    return $ret;
  }
  function copy () {
    $args = func_get_args();
    if (!isset($args[1])) $start = 0;
    else $start = $args[1];
    if (!isset($args[2])) $sourceStart = 0;
    else $sourceStart = $args[2];
    if (!isset($args[3])) $sourceEnd = strlen($this->buffer);
    else $sourceEnd = $args[3];
    $target = $args[0];
    $target->buffer = substr($target->buffer, 0, $start) . substr($this->buffer, $sourceStart, $sourceEnd - $sourceStart) . substr($this->buffer, $start + $sourceEnd - $sourceStart);
    return $this;
  }
  function equals($otherBuffer) {
    return $this->buffer === $otherBuffer->buffer;
  }
  function toString() {
    return $this->buffer;
  }
  function write($str, $offset) {
    for ($i = 0; $i < strlen($str); ++$i) {
      $this->writeUInt8(ord($str[$i]), $offset + $i);
    }
  }
  function get($offset) {
    return ord($this->buffer[$offset]);
  }
}

?>
