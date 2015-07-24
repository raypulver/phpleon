<?php

namespace LEON;

include_once 'io.php';

function type_gcd($arr) {
  $type = type_check($arr[0]);
  if (is_numeric($arr[0])) {
    $highestMagnitude = abs($arr[0]);
    $fp = is_float($arr[0]);  
    $sign = ($arr[0] < 0);
    for ($i = 1; $i < count($arr); ++$i) {
      if (!is_numeric($arr[$i])) throw new Exception('Received a non-numeric value in an array of numbers.');
      if (abs($arr[$i]) > $highestMagnitude) {
        $highestMagnitude = abs($arr[$i]);
      }
      if (is_float($arr[$i])) $fp = true;
      if ($arr[$i] < 0) $sign = true;
    }
    return type_check(($sign ? -1 : 1)*$highestMagnitude, $fp);
  } else if ($type === 0x80) {
    $comb = array();
    foreach ($arr as $v) {
      foreach ($v as $u) {
        $comb[] = $u;
      }
    }
    return array(type_gcd($comb));
  } else if ($type === 0x09) {
    $ret = array();
    foreach ($arr[0] as $k => $v) {
      $ret[$k] = type_gcd(pluck($arr, $k));
    }
    return $ret;
  } else if ($type === 0x1B) {
    $ret = array();
    foreach (get_object_vars($arr[0]) as $k => $v) {
      $ret[$k] = type_gcd(pluck($arr, $k));
    }
    return $ret;
  } else {
    if ($type === 0x21) {
      $type = 0x20;
    }
    for ($i = 1; $i < count($arr); ++$i) {
      $thisType = type_check($arr[$i]);
      if ($thisType === 0x21) $thisType = 0x20;
      if ($thisType !== $type) throw new Exception('Type mismatch.');
    }
    return $type;
  }
}

function pluck ($arr, $prop) {
  $ret = array();
  foreach ($arr as $v) {
    if (is_object($v)) {
      $ret[] = $v->{$prop};
    } else {
      $ret[] = $v[$prop];
    }
  }
  return $ret;
}

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
  static function toTemplate($payload) {
    $type = type_check($payload);
    if ($type === 0x80) {
      return array(type_gcd($payload));
    } else if ($type === 0x09) {
      $ret = array();
      foreach ($payload as $k => $v) {
        $ret[$k] = self::toTemplate($v);
      }
      return $ret;
    } else if ($type === 0x1B) {
      $ret = array();
      foreach (get_object_vars($payload) as $k => $v) {
        $ret[$k] = self::toTemplate($v);
      }
      return $ret;
    } else if ($type === 0x21) return 0x20;
    else return $type;
  }
}

?>
