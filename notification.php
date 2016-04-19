<html>
<head>
<title>TMG Integration</title>
</head>
<body>
<form id="frmUpload" enctype="multipart/form-data" method="post">
<div class="file-wrap">
<input type="hidden" name="csv_file" id="csv_file">
</div>
CSV File: <input class="fileupload mb-10" type="file" name="file">
<!-- The global progress bar -->
<div class="progress hidden">
<div class="progress-bar progress-bar-success"></div>
<input type="submit" value="Submit"><br>
</form>
</body>
</html>

<?php
require_once('phpmailer/class.phpmailer.php');
include_once('PHPExcel.php');

if (count($_REQUEST)) process($_REQUEST);

function process($data=array()) {
	$total = 0;
	$date = date('Ymd');
	$myfile = fopen("files/$date-notification.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, json_encode($data)."\n");
    fclose($myfile);

	foreach ($data as $key => $value) {
		$total++;
	}
	echo "$total records found.<br>";

	// send email notification
	sendnotif($total);
}

function sendnotif($total=0) {
	$adminEmail = "paul.gonzaga@compareasiagroup.com";
	$adminName = "MoneyMax Admin";

    $mail = new PHPMailer(); // defaults to using php "mail()"
    $mail->IsSendmail(); // telling the class to use SendMail transport

    $body = "This is to inform you that we already processed $total records.<br>";
	$body .= "MoneyMax Admin";

	$mail->AddReplyTo($adminEmail, $adminName);
	$mail->SetFrom($adminEmail, $adminName);
	$mail->AddReplyTo($adminEmail, $adminName);

	// recipients
	$mail->AddAddress("paul.gonzaga@compareasiagroup.com", "Paul Gonzaga");
	$mail->AddAddress("giacomo.puccini@moneymax.ph", "Giacomo Puccini");

	$mail->Subject = "Pipedrive Notification";
	$mail->AltBody = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mail->MsgHTML($body);

	if (!$mail->Send()) {
  		echo "Mailer Error: " . $mail->ErrorInfo;
	} else {
  		echo "Message sent!";
	}
}

?>
