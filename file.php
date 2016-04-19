<?php

$DELETE = "the_line_you_want_to_delete";
$data = file("./foo.txt");

$out = array();
foreach($data as $line) {
	$pipe = explode("|", $line);
	if (trim($pipe[0]) != $DELETE) {
     	$out[] = $line;
   	}
}

$fp = fopen("./foo.txt", "w+");
flock($fp, LOCK_EX);
foreach($out as $line) {
	fwrite($fp, $line);
}
flock($fp, LOCK_UN);
fclose($fp);

?>
