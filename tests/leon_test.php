<?php
  include_once 'leon.php';
  class LeonTest extends PHPUnit_Framework_TestCase {
    function test_bijection() {
      $payload = array();
      $payload['a'] = 1;
      $payload['b'] = 2;
      $this->assertEquals(leon_decode(leon_encode($payload)), $payload);
    }
    function test_channel() {
      $payload = array();
      $template = array();
      $template['c'] = LEON_STRING;
      $template['d'] = array();
      $template['d'][] = array();
      $template['d'][0]['a'] = LEON_SIGNED_CHAR;
      $template['d'][0]['b'] = LEON_BOOLEAN;
      $channel = new LEON_Channel($template);
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
      $channel = new LEON_Channel($template);
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
  }
?>
