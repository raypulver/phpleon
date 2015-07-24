<?php

namespace LEON;

include_once 'regexp.php';
include_once 'date.php';
include_once 'nan.php';
include_once 'undefined.php';
include_once 'string-buffer.php';
include_once 'infinity.php';

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
define("INFINITY", 0x19);
define("MINUSINFINITY", 0x1A);
define("NATIVEOBJECT", 0x1B);
define("EMPTYINDEX", 0xFF);
function is_hash($arr) {
  return array_keys($arr) != range(0, count($arr) - 1);
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
    $len = $this->buffer->readValue($this->buffer->readUInt8());
    for ($i = 0; $i < $len; ++$i) {
      $ret .= chr($this->buffer->readUInt8());
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
    return new RegExp($this->readString(), $this->readString());
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
        $keys = array_keys($spec);
        sort($keys);
        foreach ($keys as $k) {
           $ret[$k] = $this->parseValueWithSpec($spec[$k]);
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
    } else if ($type === INFINITY) {
      return new Infinity();
    } else if ($type === MINUSINFINITY) {
      return new MinusInfinity();
    } else {
      return;
    }
  }
}
function type_check () {
  $args = func_get_args();
  $val = $args[0];
  if (count($args) > 1) $fp = $args[1];
  else $fp = false;
  if ($val === NULL) return NULLVAL;
  if ($val === TRUE) return TRUEVAL;
  if ($val === FALSE) return FALSEVAL;
  if (is_object($val)) {
    if ($val instanceof NaN) return NANV;
    if ($val instanceof Undefined) return UNDEFINED;
    if ($val instanceof Date) return DATEVAL;
    if ($val instanceof Buffer) return BUFFER;
    if ($val instanceof RegExp) return REGEXP;
    if ($val instanceof Infinity) return INFINITY;
    if ($val instanceof MinusInfinity) return MINUSINFINITY;
    return NATIVEOBJECT;
  }
  if (is_array($val)) {
    if (is_hash($val)) return OBJECTV;
    return VARARRAY;
  }
  if (is_string($val)) return STRINGV;
  if (is_numeric($val)) {
    if ($fp || is_double($val)) {
      $sig = abs($val);
      $log = log($sig)/log(2);
      $log = ($log < 0 ? ceil($log) : floor($log));
      $exp = 105 + $log;
      if ($exp < 0 || $exp > 256) return DOUBLEV;
      $sig *= pow(2, -$log + 23);
      if (floor($sig) != $sig) {
        return DOUBLEV;
      }
      return FLOATV;
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
        $keys = array_keys($spec);
        sort($keys);
        if (is_object($val)) {
          foreach ($keys as $k) {
            $this->writeValueWithSpec($val->{$k}, $spec[$k]);
          }
        } else {
          foreach ($keys as $k) {
            $this->writeValueWithSpec($val[$k], $spec[$k]);
          }
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
    if ($type === NATIVEOBJECT) {
      $typeByte->writeUInt8(OBJECTV, 0);
    } else {
      $typeByte->writeUInt8($type, 0);
    }
    if ($type === UNDEFINED || $type === TRUEVAL || $type === FALSEVAL || $type === NULLVAL || $type === NANV || $type === MINUSINFINITY || $type === INFINITY) {
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
    } else if ($type === NATIVEOBJECT) {
      $index = match_layout($val, $this->stringIndex, $this->OLI);
      $this->writeValue($index, $this->OLItype, true);
      for ($i = 0; $i < count($this->OLI[$index]); ++$i) {
        $tmp = $val->{$this->stringIndex[$this->OLI[$index][$i]]};
        $this->writeValue($tmp, type_check($tmp));
      }
      return;
    } else if ($type === BUFFER) {
      $len = strlen($val->buffer);
      $this->writeValue($len, type_check($len));
      for ($i = 0; $i < $len; ++$i) {
        $this->writeValue(ord($val->buffer[$i]), CHAR, true); 
      }
      return $byteCount + strlen($val->buffer);
    } else if ($type === REGEXP) {
      $this->writeString($val->pattern);
      $this->writeString($val->modifier);
      return $byteCount + 2 + strlen($val->pattern) + strlen($val->modifier);
    } else if ($type === DATEVAL) {
      $this->writeValue($val->timestamp, INTV, true);
      return $byteCount + 4;
    }
  }
  function writeString ($str) {
    $len = strlen($str);
    $this->writeValue($len, type_check($len));
    for ($i = 0; $i < $len; ++$i) {
      $this->writeValue(ord($str[$i]), CHAR, true);
    }
    return;
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
  if (is_object($val)) {
    $keys = array();
    foreach (get_object_vars($val) as $k => $v) {
      $keys[] = $k;
    }
  } else {
    $keys = array_keys($val);
  }
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
  if (is_object($branch)) {
    $ret[] = array();
    $vars = get_object_vars($branch);
    foreach ($vars as $k => $v) {
      $ret[count($ret) - 1][] = array_search($k, $stringIndex);
    }
    foreach ($vars as $k => $v) {
      gather_layouts($val, $stringIndex, $ret, $v);
    }
  } else if (is_array($branch)) {
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
  if (is_object($branch)) {
    $vars = get_object_vars($branch);
    foreach ($vars as $k => $v) {
      set_push($ret, $k);
    }
    foreach ($vars as $k => $v) {
      gather_strings($val, $ret, $v);
    }
  } else if (is_array($branch)) {
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
