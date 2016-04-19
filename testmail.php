<?php

require_once('phpmailer/class.phpmailer.php');

sendmail('test message');
function sendmail($message='') {
	$mail = new PHPMailer(); // defaults to using php "mail()"
//	$mail->IsSendmail(); // telling the class to use SendMail transport
	$mail->IsSMTP();                             // tell the class to use SMTP
    $mail->SMTPAuth   = true;                    // enable SMTP authentication
    $mail->Port       = 465; // 25;                      // set the SMTP server port
    $mail->Host       = "ssl://smtp.gmail.com";        // SMTP server
    $mail->Username   = "pipedrive@moneymax.ph";  // SMTP server username
    $mail->Password   = "9amPolicyMeeting";            // SMTP server password

	$body	= "To view the message, please use an HTML compatible email viewer!";
	$body	= eregi_replace("[\]",'',$body);

$mail->AddReplyTo("paul.gonzaga@compareasiagroup.com","Paul Gonzaga");
$mail->SetFrom('paul.gonzaga@compareasiagroup.com', 'Paul Gonzaga');
$mail->AddReplyTo("paul.gonzaga@compareasiagroup.com","Paul Gonzaga");

$address = "paul.gonzaga@compareasiagroup.com";
$mail->AddAddress($address, "Paul Gonzaga");

$mail->Subject    = "PHPMailer Test Subject via Sendmail, basic";
$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

$mail->MsgHTML($body);

// $mail->AddAttachment("images/phpmailer.gif");      // attachment
// $mail->AddAttachment("images/phpmailer_mini.gif"); // attachment

if(!$mail->Send()) {
  echo "Mailer Error: " . $mail->ErrorInfo;
} else {
  echo "Message sent!";
}
}
?>
