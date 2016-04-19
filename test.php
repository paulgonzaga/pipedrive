<?php 

$datetime = "15/04/2016";
$dates = explode("/", $datetime);
$datetime = @$dates[1]."/".@$dates[0]."/".@$dates[2]; 
echo "$datetime";
?>
