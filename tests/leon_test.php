<?php
  include_once 'leon.php';
  use LEON\Channel;
  use LEON\RegExp;
  use LEON\Date;
  use LEON\Undefined;
  class Dummy {
    public $woop = null;
    public $doop = null;
    function __construct() {
      $args = func_get_args();
      $val = $args[0];
      $do = $args[1];
      $this->woop = $val;
      $this->doop = $do;
    }
  }
  class LEONTest extends PHPUnit_Framework_TestCase {
    function test_bijection() {
      $payload = array();
      $payload['a'] = 1;
      $payload['b'] = 2;
      $this->assertEquals(leon_decode(leon_encode($payload)), $payload);
    }
    function test_signed() {
      $this->assertEquals(leon_decode(leon_encode(-500)), -500);
    }
    function test_channel() {
      $payload = array();
      $template = array();
      $template['c'] = LEON_STRING;
      $template['d'] = array();
      $template['d'][] = array();
      $template['d'][0]['a'] = LEON_CHAR;
      $template['d'][0]['b'] = LEON_BOOLEAN;
      $channel = new Channel($template);
      $obj = array();
      $obj['c'] = "woop";
      $obj['d'] = array();
      $obj['d'][] = array();
      $obj['d'][0]['a'] = 125;
      $obj['d'][0]['b'] = TRUE;
      $obj['d'][] = array();
      $obj['d'][1]['a'] = 124;
      $obj['d'][1]['b'] = FALSE;
      $this->assertEquals($channel->decode($channel->encode($obj)), $obj);
    }
    function test_channel2() {
      $template = array();
      $template['strings'] = array();
      $template['strings'][] = LEON_STRING;
      $template['numbers'] = array();
      $template['numbers'][] = LEON_INT;
      $channel = new Channel($template);
      $payload = array();
      $payload['strings'] = array();
      $payload['strings'][] = "the";
      $payload['strings'][] = "dog";
      $payload['strings'][] = "ate";
      $payload['strings'][] = "the";
      $payload['strings'][] = "cat";
      $payload['numbers'] = array();
      $payload['numbers'][] = 100;
      $payload['numbers'][] = 1000;
      $payload['numbers'][] = 10000;
      $payload['numbers'][] = 100000;
      $this->assertEquals($channel->decode($channel->encode($payload)), $payload);
    }
    function test_float() {
      $payload = -0.5;
      $this->assertEquals(leon_decode(leon_encode($payload)), $payload);
    }
    function test_double() {
      $payload = -232.222;
      $channel = new LEON\Channel(LEON_DOUBLE);
      $this->assertEquals($payload, $channel->decode($channel->encode($payload)));
    }
    function test_regexp() {
      $regexp = new RegExp('^$');
      $this->assertEquals(leon_decode(leon_encode($regexp))->toString(), '/^$/');
    }
    function test_date() {
      $time = 1437149199;
      $date = new LEON\Date($time);
      $this->assertEquals(leon_decode(leon_encode($date))->timestamp, $time);
    }
    function test_nan() {
      $this->assertEquals(leon_decode(leon_encode(new LEON\NaN())) instanceof LEON\NaN, true);
    }
    function test_undefined() {
      $this->assertEquals(leon_decode(leon_encode(new LEON\Undefined())) instanceof LEON\Undefined, true);
    }
    function test_regexp_with_modifier() {
      $this->assertEquals(leon_decode(leon_encode(new LEON\RegExp('54', 'i')))->toString(), '/54/i');
    }
    function test_object() {
      $bounce = leon_decode(leon_encode(new Dummy('woop', 'doop')));
      $this->assertEquals($bounce['woop'], 'woop');
      $this->assertEquals($bounce['doop'], 'doop');
    }
    function test_float_detection () {
      $ser = leon_encode(((1 << 24) - 1) * pow(2, -127));
      $this->assertEquals(6, ord($ser[1]));
      $ser = leon_encode(((1 << 24) - 1) * pow(2, -1 - 127));
      $this->assertEquals(ord($ser[1]), 7);
      $ser = leon_encode(((1 << 24) + 1) * pow(2, -127));
      $this->assertEquals(ord($ser[1]), 7);
    }
    function test_template() {
      $obj = array(
        'woopdoop' => 5,
        'shoopdoop' => array(510, -510, 1, 0.5),
        'doopwoop' => array(array(
          'a' => true,
          'b' => 5,
          'c' => array(5, 2, 1),
          'd' => 'woop',
          'e' => new LEON\Date(1300000000)
        ))
      );
      $template = LEON\Channel::toTemplate($obj);
      $this->assertEquals($template, array(
        'woopdoop' => LEON_UNSIGNED_CHAR,
        'shoopdoop' => array(LEON_FLOAT),
        'doopwoop' => array(array(
          'a' => LEON_BOOLEAN,
          'b' => LEON_UNSIGNED_CHAR,
          'c' => array(LEON_UNSIGNED_CHAR),
          'd' => LEON_STRING,
          'e' => LEON_DATE
        ))
      ));
    }
    function test_bytelength_detection () {
      $this->assertEquals(ord(leon_encode(-128)[1]), LEON_CHAR);
      $this->assertEquals(ord(leon_encode(-129)[1]), LEON_SHORT);
      $this->assertEquals(ord(leon_encode(255)[1]), LEON_UNSIGNED_CHAR);
      $this->assertEquals(ord(leon_encode(256)[1]), LEON_UNSIGNED_SHORT);
    }
  }
?>
