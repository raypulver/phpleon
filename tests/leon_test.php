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
      $EPS = 0.0001;
      $payload = -232.2222;
      $this->assertEquals(abs(leon_decode(leon_encode($payload)) - $payload) < $EPS, TRUE);
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
      $ser = leon_encode(((1 << 23) - 1) * pow(2, -((1 << 7) - 1)));
      $this->assertEquals(ord($ser[1]), 6);
      $ser = leon_encode(((1 << 23)) * pow(2, -((1 << 8) - 1)));
      $this->assertEquals(ord($ser[1]), 7);
    }
  }
?>
