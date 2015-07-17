<?php
  include_once '../leon.php';
  $ser = leon_encode(new LEON\NaN());
  for ($i = 0; $i < strlen($ser); ++$i) {
    print ord($ser[$i]) . "\n";
  }
?>
