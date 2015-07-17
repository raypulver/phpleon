<?php
    require_once '../leon.php';
    $value = array(array(
      'key' => array(5, 4, 3, 2, 1),
      'secondkey' => true,
      'thirdkey' => 500,
      'fourthkey' => new LEON\Date(time())
    ), array(
      'key' => array(100, 200, 150),
      'secondkey' => false,
      'thirdkey' => true,
      'fourthkey' => new LEON\Date(time())
    ));
    $ser = leon_encode($value);
    print_r(leon_decode($ser));
    print_r("JSON length is " . strlen(json_encode($value)) . "\n");
    print_r("LEON length is " . strlen($ser) . "\n");
    $channel = new LEON\Channel(array(array(
      'key' => array(LEON_CHAR),
      'secondkey' => LEON_BOOLEAN,
      'thirdkey' => LEON_SHORT,
      'fourthkey' => LEON_DATE
    )));
    print_r("Channel serialization length is " . strlen($channel->encode($value)) . "\n");
    $ser = leon_encode(new LEON\Date(time()));
    for ($i = 0; $i < strlen($ser); ++$i) {
      print ord($ser[$i]) . "\n";
    }
?>
