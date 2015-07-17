<?php

namespace LEON;

include_once 'regexp.php';
include_once 'date.php';
include_once 'nan.php';
include_once 'undefined.php';

define("SIGNED", 0x01);
define("CHAR", 0x00);
define("SHORT", 0x02);
define("INTV", 0x04);
define("FLOATV", 0x06);
define("DOUBLEV", 0x07);
define("VARARRAY", 0x80);
define("OBJECTV", 0x09);
define("STRINGV", 0x10);
define("TRUEVAL", 0x20);
define("FALSEVAL", 0x21);
define("NULLVAL", 0x40);
define("UNDEFINED", 0x14);
define("DATEVAL", 0x15);
define("BUFFER", 0x16);
define("REGEXP", 0x17);
define("NANV", 0x18);
define("EMPTYINDEX", 0xFF);
function is_hash($arr) {
  return array_keys($arr) != range(0, count($arr) - 1);
}
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
  function writeInt16LE($v, $i) {
    $v = intval($v);
    $v = ($v < 0 ? complement(-$v, 16) : $v);
    return $this->writeUInt16LE($v, $i);
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
  function writeInt32LE($v, $i) {
    $v = intval($v);
    $v = ($v < 0 ? complement(-$v, $i) : $v);
    return $this->writeUInt32LE($v, $i);
  }
  function writeFloatLE($v, $i) {
    $bytes = bytes($v, FLOATV);
    foreach (array_reverse($bytes) as $idx => $b) {
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
  function readInt16LE($i) {
    $v = $this->readUInt16LE($i);
    if (($v & 0x8000) !== 0) { return -complement($v, 16); }
    return $v;
  }
  function readUInt32LE($i) {
    return ord($this->buffer[$i]) | (ord($this->buffer[$i + 1]) << 8) | (ord($this->buffer[$i + 2]) << 16) | (ord($this->buffer[$i + 3]) << 24);
  }
  function readInt32LE($i) {
    $v = $this->readUInt32LE($i);
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
  function readDoubleLE($i) {
    $bytes = array();
    for ($j = 0; $j < 8; ++$j) {
      $bytes[] = $this->readUInt8($i + $j);
    }
    return bytes_to_double(array_reverse(bytes));
  }
}
class BufferIterator {
  public $buffer = NULL;
  public $i = 0;
  function __construct($buf) {
    $this->buffer = $buf;
  }
  function readUInt8() {
    $this->i++;
    return $this->buffer->readUInt8($this->i - 1);
  }
  function readInt8() {
    $this->i++;
    return $this->buffer->readInt8($this->i - 1);
  }
  function readUInt16() {
    $this->i += 2;
    return $this->buffer->readUInt16LE($this->i - 2);
  }
  function readInt16() {
    $this->i += 2;
    return $this->buffer->readInt16LE($this->i - 2);
  }
  function readUInt32() {
    $this->i += 4;
    return $this->buffer->readUInt32LE($this->i - 4);
  }
  function readInt32() {
    $this->i += 4;
    return $this->buffer->readInt32LE($this->i - 4);
  }
  function readFloat() {
    $this->i += 4;
    return $this->buffer->readFloatLE($this->i - 4);
  }
  function readDouble() {
    $this->i += 8;
    return $this->buffer->readDoubleLE($this->i - 8);
  }
  function readValue($type) {
    if ($type === CHAR) return $this->readUInt8();
    if ($type === (SIGNED | CHAR)) return $this->readInt8();
    if ($type === SHORT) return $this->readUInt16();
    if ($type === (SIGNED | SHORT)) return $this->readInt16();
    if ($type === INTV) return $this->readUInt32();
    if ($type === (SIGNED | INTV)) return $this->readInt32();
    if ($type === FLOATV) return $this->readFloat();
    if ($type === DOUBLEV) return $this->readDouble();
  }
}
class Parser {
  public $buffer = NULL;
  public $state = 0;
  public $stringIndex = array();
  public $objectLayoutIndex = array();
  public $spec = NULL;
  public $stringIndexType = NULL;
  public $OLItype = NULL;
  function __construct() {
    $args = func_get_args();
    if (count($args) >= 2) $this->spec = $args[1];
    if (count($args) >= 1) $this->buffer = new BufferIterator($args[0]);
  }
  function readString() {
    $ret = "";
    for (;;) {
      $char = $this->buffer->readUInt8();
      if ($char === 0) break;
      $ret .= chr($char);
    }
    return $ret;
  }
  function readBuffer() {
    $ret = new StringBuffer();
    $len = $this->buffer->readValue($this->buffer->readUInt8());
    for ($i = 0; $i < $len; ++$i) {
      $ret->writeUInt8($this->buffer->readUInt8(), $i);
    }
    return $ret;
  }
  function readRegExp() {
    return new RegExp($this->readString());
  }
  function readDate() {
    return new Date($this->buffer->readUInt32());
  }
  function parseSI() {
    if ($this->state & 0x01 !== 0) return;
    $this->stringIndexType = $this->buffer->readUInt8();
    switch ($this->stringIndexType) {
      case CHAR:
      case SHORT:
      case INTV:
        $stringCount = $this->buffer->readValue($this->stringIndexType);
        break;
      case EMPTYINDEX:
        $stringCount = 0;
        break;
      default:
        return $this;
    }
    for ($i = 0; $i < $stringCount; ++$i) {
      $this->stringIndex[] = $this->readString();
    }
    $this->state |= 0x01;
    return $this;
  }
  function parseOLI() {
    if ($this->state & 0x01 === 0) $this->parseSI();
    if (count($this->stringIndex) === 0) return $this;
    $this->OLItype = $this->buffer->readUInt8();
    switch ($this->OLItype) {
      case CHAR:
      case SHORT:
      case INTV:
        $count = $this->buffer->readValue($this->OLItype);
        break;
      case EMPTYINDEX:
        return $this;
      default:
        return $this;
    }
    for ($i = 0; $i < $count; ++$i) {
      $this->objectLayoutIndex[] = array();
      $numFields = $this->buffer->readValue($this->buffer->readUInt8());
      for ($j = 0; $j < $numFields; ++$j) {
        $this->objectLayoutIndex[$i][] = $this->buffer->readValue($this->stringIndexType);
      }
    }
    return $this;
  }
  function parseValueWithSpec () {
    $args = func_get_args();
    if (count($args) === 0) $spec = $this->spec;
    else $spec = $args[0];
    if ($spec === STRINGV) return $this->readString();
    else if (is_array($spec)) {
      $ret = array();
      if (is_hash($spec)) {
        foreach ($spec as $k => $v) {
           $ret[$k] = $this->parseValueWithSpec($v);
        }
        return $ret;
      }
      $spec = $spec[0];
      $length = $this->buffer->readValue($this->buffer->readUInt8());
      for ($i = 0; $i < $length; ++$i) {
        $ret[] = $this->parseValueWithSpec($spec);
      }
      return $ret;
    } else if ($spec === (TRUEVAL & FALSEVAL)) {
      return $this->parseValue();
    } else {
      return $this->parseValue($spec);
    }
  }
  function parseValue () {
    $args = func_get_args();
    if (count($args) === 0) $type = $this->buffer->readUInt8();
    else $type = $args[0];
    if ($type < OBJECTV) {
      return $this->buffer->readValue($type);
    } else if ($type === VARARRAY) {
      $length = $this->buffer->readValue($this->buffer->readUInt8());
      $ret = array();
      for ($i = 0; $i < $length; ++$i) {
        $ret[] = $this->parseValue();
      }
      return $ret;
    } else if ($type === OBJECTV) {
      $index = $this->objectLayoutIndex[$this->buffer->readValue($this->OLItype)];
      $ret = array();
      foreach ($index as $idx => $v) {
        $ret[$this->stringIndex[$v]] = $this->parseValue();
      }
      return $ret;
    } else if ($type === STRINGV) {
      return $this->stringIndex[$this->buffer->readValue($this->stringIndexType)];
    } else if ($type === UNDEFINED) {
      return new Undefined();
    } else if ($type === TRUEVAL) { 
      return TRUE;
    } else if ($type === FALSEVAL) {
      return FALSE;
    } else if ($type === NULLVAL) {
      return NULL;
    } else if ($type === NANV) {
      return new NaN();
    } else if ($type === DATEVAL) {
      return $this->readDate();
    } else if ($type === REGEXP) {
      return $this->readRegExp();
    } else if ($type === BUFFER) {
      return $this->readBuffer();
    } else {
      return;
    }
  }
}
function type_check ($val) {
  if ($val === NULL) return NULLVAL;
  if ($val === TRUE) return TRUEVAL;
  if ($val === FALSE) return FALSEVAL;
  if (is_object($val)) {
    if ($val instanceof NaN) return NANV;
    if ($val instanceof Undefined) return UNDEFINED;
    if ($val instanceof Date) return DATEVAL;
    if ($val instanceof Buffer) return BUFFER;
    if ($val instanceof RegExp) return REGEXP;
  }
  if (is_array($val)) {
    if (is_hash($val)) return OBJECTV;
    return VARARRAY;
  }
  if (is_string($val)) return STRINGV;
  if (is_numeric($val)) {
    if (is_double($val)) {
      if (is_float($val)) return FLOATV;
      return DOUBLEV;
    }
    if ($val < 0) {
      $val = abs($val);
      if ($val < 1 << 6) return SIGNED | CHAR;
      if ($val < 1 << 14) return SIGNED | SHORT;
      if ($val < 1 << 30) return SIGNED | INTV;
      return DOUBLEV;
    }
    if ($val < 1 << 7) return CHAR;
    if ($val < 1 << 15) return SHORT;
    if ($val < pow(2, 32)) return INTV;
    return DOUBLEV;
  }
}
class Encoder {
  public $payload = NULL;
  public $buffer = NULL;
  public $spec = NULL;
  public $stringIndex = array();
  public $OLI = array();
  public $OLItype = NULL;
  public $stringIndexType = NULL;
  function __construct () {
    $args = func_get_args();
    $this->payload = $args[0];
    $this->buffer = new StringBuffer();
    if (count($args) >= 2) $this->spec = $args[1];
  }
  function append ($buf) {
    $this->buffer = StringBuffer::concat(array($this->buffer, $buf));
  }
  function writeData () {
    if (!is_null($this->spec)) $this->writeValueWithSpec($this->payload);
    else $this->writeValue($this->payload, type_check($this->payload));
    return $this;
  }
  function export () {
    return $this->buffer->buffer;
  }
  function writeValueWithSpec () {
    $args = func_get_args();
    $val = $args[0];
    if (count($args) >= 2) $spec = $args[1];
    else $spec = $this->spec;
    if (is_array($spec)) {
      if (is_hash($spec)) {
        foreach ($spec as $k => $v) {
          $this->writeValueWithSpec($val[$k], $v);
        }
      } else { 
        $this->writeValue(count($val), type_check(count($val)));
        foreach ($val as $v) {
          $this->writeValueWithSpec($v, $spec[0]);
        }
      }
    } else if ($spec === (TRUEVAL & FALSEVAL)) {
      $this->writeValue($val, type_check($val), TRUE);
    } else {
      $this->writeValue($val, $spec, TRUE);
    }
  }
  function writeValue () {
    $args = func_get_args();
    $val = $args[0];
    $type = $args[1];
    if (count($args) >= 3) $implicit = $args[2];
    else $implicit = FALSE;
    $typeByte = new StringBuffer();
    $typeByte->writeUInt8($type, 0);
    if ($type === UNDEFINED || $type === TRUEVAL || $type === FALSEVAL || $type === NULLVAL || $type === NANV) {
      $this->append($typeByte);
      return 1;
    }
    $byteCount = 0;
    if (!$implicit) {
      $this->append($typeByte);
      $byteCount++;
    }
    if ($type === STRINGV) {
      if (count($this->stringIndex) === 0) {
        $this->writeString($val);
        return 1 + $byteCount + strlen($val);
      }
      $this->writeValue(array_search($val, $this->stringIndex), $this->stringIndexType, TRUE);
      return $byteCount + 1;
    } else if ($type === (SIGNED | CHAR)) {
      $bytes = new StringBuffer();
      $bytes->writeInt8($val, 0);
      $this->append($bytes);
      return $byteCount + 1;
    } else if ($type === CHAR) {
      $bytes = new StringBuffer();
      $bytes->writeUInt8($val, 0);
      $this->append($bytes);
      return $byteCount + 1;
    } else if ($type === (SIGNED | SHORT)) {
      $bytes = new StringBuffer();
      $bytes->writeInt16LE($val, 0);
      $this->append($bytes);
      return 2 + $byteCount;
    } else if ($type === SHORT) {
      $bytes = new StringBuffer();
      $bytes->writeUInt16LE($val, 0);
      $this->append($bytes);
      return 2 + $byteCount;
    } else if ($type === (SIGNED | INTV)) {
      $bytes = new StringBuffer();
      $bytes->writeInt32LE($val, 0);
      $this->append($bytes);
      return 4 + $byteCount;
    } else if ($type === INTV) {
      $bytes = new StringBuffer();
      $bytes->writeUInt32LE($val, 0);
      $this->append($bytes);
      return 4 + $byteCount;
    } else if ($type === FLOATV) {
      $bytes = new StringBuffer();
      $bytes->writeFloatLE($val, 0);
      $this->append($bytes);
      return 4 + $byteCount;
    } else if ($type === DOUBLEV) {
      $bytes = new StringBuffer();
      $bytes->writeDoubleLE($val, 0);
      $this->append($bytes);
      return 8 + $byteCount;
    } else if ($type === VARARRAY) {
      $this->writeValue(count($val), type_check(count($val)));
      foreach ($val as $v) {
        $this->writeValue($v, type_check($v));
      }
      return;
    } else if ($type === OBJECTV) {
      $index = match_layout($val, $this->stringIndex, $this->OLI);
      $this->writeValue($index, $this->OLItype, TRUE);
      for ($i = 0; $i < count($this->OLI[$index]); ++$i) {
        $tmp = $val[$this->stringIndex[$this->OLI[$index][$i]]];
        $this->writeValue($tmp, type_check($tmp));
      }
      return;
    } else if ($type === BUFFER) {
      $len = strlen($val->buffer);
      $this->writeValue($len, type_check($len));
      for ($i = 0; $i < $len; ++$i) {
        $this->writeValue(ord($val->buffer[$i]), CHAR, true); 
      }
      return $typeCheck + strlen($val->buffer);
    } else if ($type === REGEXP) {
      $this->writeString($val->toString());
      return $byteCount + strlen($val->toString());
    } else if ($type === DATEVAL) {
      $this->writeValue($val->timestamp, INTV, true);
      return $byteCount + 4;
    }
  }
  function writeString ($str) {
    $bytes = new StringBuffer();
    $len = strlen($str);
    for ($i = 0; $i < $len; ++$i) {
      $bytes->writeUInt8(ord($str[$i]), -1);
    }
    $bytes->writeUInt8(0, -1);
    $this->append($bytes);
    return $len + 1;
  }
  function writeOLI () {
    if (count($this->stringIndex) === 0) return $this;
    $index = array();
    $this->OLI = gather_layouts($this->payload, $this->stringIndex, $index, $this->payload);
    if (count($this->OLI) === 0) {
      $this->writeValue(EMPTYINDEX, CHAR, TRUE);
      return $this;
    }
    $this->OLItype = type_check(count($this->OLI));
    $this->writeValue(count($this->OLI), $this->OLItype);
    foreach ($this->OLI as $v) {
      $type = type_check(count($v));
      $this->writeValue(count($v), $type);
      foreach ($v as $u) {
        $this->writeValue($u, $this->stringIndexType, TRUE);
      }
    }
    return $this;
  }
  function writeSI () {
    $index = array();
    $this->stringIndex = gather_strings($this->payload, $index, $this->payload);
    if (count($this->stringIndex) === 0) {
      $this->writeValue(EMPTYINDEX, CHAR, TRUE);
      return $this;
    }
    $this->stringIndexType = type_check(count($this->stringIndex));
    $this->writeValue(count($this->stringIndex), $this->stringIndexType);
    foreach ($this->stringIndex as $s) {
      $this->writeString($s);
    }
    return $this;
  }
}
function match_layout ($val, $stringIndex, $OLI) {
  $keys = array_keys($val);
  $layout = array();
  for ($i = 0; $i < count($keys); ++$i) {
    $layout[] = array_search($keys[$i], $stringIndex);
  }
  $layout = sort($layout);
  $i = 0;
  while ($i < count($OLI)) {
    if ($layout == sort($OLI[$i])) {
      return $i;
    }
    ++$i;
  }
}
function gather_layouts($val, $stringIndex, &$ret, $branch) {
  if (!isset($ret)) $ret = array();
  if (!isset($branch)) $branch = $val;
  if (is_array($branch)) {
    if (is_hash($branch)) {
      $ret[] = array();
      foreach ($branch as $k => $v) {
        $ret[count($ret) - 1][] = array_search($k, $stringIndex);
      }
    }
    foreach ($branch as $k => $v) {
      gather_layouts($val, $stringIndex, $ret, $v);
    }
  }
  return $ret;
}
function gather_strings($val, &$ret, $branch) {
  if (!isset($ret)) $ret = array();
  if (!isset($branch)) $branch = $val;
  if (is_array($branch)) {
    if (is_hash($branch)) {
      foreach ($branch as $k => $v) {
        set_push($ret, $k);
      }
    }
    foreach ($branch as $k => $v) {
      gather_strings($val, $ret, $branch[$k]);
    }
  } else if (is_string($branch)) {
    set_push($ret, $branch);
  }
  return $ret;
}
function set_push(&$arr, $v) {
  if (!in_array($v, $arr)) $arr[] = $v;
}
?>
