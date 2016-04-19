<html>
<head>
<title>Pipedrive Integration</title>
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
require_once("phpmailer/class.phpmailer.php");
include_once("PHPExcel.php");

if (@$_FILES['file']) :
$csvfile = upload();
if ($csvfile) process($csvfile);
endif;

// this is for gmail
if (@$_GET['gfile']) process(@$_GET['gfile']);


function upload() {
	$uploadOk = 1;
	$fileName = basename($_FILES["file"]["name"]);
	$targetDir = "files/input/";
	$targetFile = $targetDir.$fileName;
	$fileType = pathinfo($targetFile,PATHINFO_EXTENSION);

	// Check file size
	if (@$_FILES["file"]["size"] > 500000) {
    	echo "Sorry, your file is too large.<br>";
	    $uploadOk = 0;
	}

	// Allow certain file formats
	if ($fileType != "csv") {
    	echo "Sorry, only CSV files are allowed.<br>";
	    $uploadOk = 0;
	}

	// Check if file already exists
    $ctr=0;
    $csvfile = $fileName;
    while (file_exists($targetFile)) {
        $ctr++;
        $csvfile = "$ctr-$fileName";
        $targetFile = $targetDir.$csvfile;
    }

	// Check if $uploadOk is set to 0 by an error
	if ($uploadOk == 0) {
    	echo "Sorry, your file was not uploaded.<br>";
	// if everything is ok, try to upload file
	} else {
    	if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
        	echo "The file ". basename( $_FILES["file"]["name"]) . " has been uploaded.<br>";
			return $csvfile;
	    } else {
    	    echo "Sorry, there was an error uploading your file.<br>";
	    }
	}
}

function process($csvfile="") {
	if (!$csvfile) return 1; 
	$inputFileType = "CSV";
	$inputFileName = "files/input/".$csvfile;

	$first = 1; $total = $deals = $persons = 0;
	$file = fopen($inputFileName,"r");
	while(!feof($file)) {
  		$data = fgetcsv($file);
		$line = $data[0];
		$fields = explode(";", $line);

		if ($first) : $first = 0;
		else : 
			$total++;
			$datetime = @$fields[0];
			$dates = explode("/", $datetime);
			$datetime = @$dates[1]."/".@$dates[0]."/".@$dates[2];
			$fields[0] = date("Y-m-d H:i:s", strtotime("$datetime +8 hour"));
			$email = @$fields[5];
            $phone = @$fields[6];

			// temporary
//			$email = "";
			$tdate = date("Y-m-d", strtotime($fields[0]));
			if (strtotime($tdate)>=strtotime("2016-04-06") && $email) :
				$personId = searchPerson($email, $phone);
				if (!$personId) :
					echo "person: $email does not exist!<br>";
					$personId = addPerson($email, $fields);
					$persons ++;
				endif;

				$title = trim($fields[3])." Deal $personId";
				$dealId = searchDeal($title, $personId);
				if (!$dealId) :
					echo "deal: $title does not exist!<br>";
					$dealId = addDeal($title, $personId, $fields);
					$deals ++;
				endif;

			// temporary
			endif;
		endif;
  	}
	fclose($file);
	echo "$total records found.<br>";

	// send email notification
	sendnotif($csvfile, $total, $persons, $deals);
}

function searchPerson($email="", $phone="") 
{
	if (!$email) return 0;
	$param = array();
	$param["search_by_email"] = 1;
	$param["term"] = $email;
	$param["limit"] = 1;

	$myfile = fopen("files/contacts.txt", "a+") or die("Unable to open file!");
	$id = 0;
	while(!feof($myfile)) {
		$line = trim(fgets($myfile));
		$pipe = explode("|", $line);
		if (strtolower(@$pipe[1])==strtolower($email)) $id = @$pipe[0];
	}
	fclose($myfile);
	if ($id) return $id;

	$data = callAPI("persons/find", "GET", $param);
	$json = json_decode($data, true);
 	$id = $json["data"][0]["id"];

	if ($id) :
	$myfile = fopen("files/contacts.txt", "a+") or die("Unable to open file!");
	fwrite($myfile, "$id|$email\n");
	fclose($myfile);
	endif;

	sleep(1);
	return $id;
}

function addPerson($email="", $fields=array())
{
	if (!$email) return 0;
	$param = array();
	$param["visible_to"] = 3; // visible to all
    $param["name"] = $fields[3];
    $param["email"] = $fields[5];
	$param["phone"] = $fields[6];
	$param["owner_id"] = '<your ownerid here>'; // Back Office
	$param["e3c0c9648dc2cc175f3ca0415c519edefd4b4267"] = date("Y-m-d", strtotime($fields[0])); // date of lead
    $param["fd86114e5da793680edc151106342c4495553daa"] = date("H:i:s", strtotime($fields[0])); // time of lead
		
    $data = callAPI("persons", "POST", $param);
    $json = json_decode($data, true);
	echo "new person: ".json_encode($json)."<br>";
	$id = $json["data"]["id"];

	$myfile = fopen("files/contacts.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "$id|$email\n");
    fclose($myfile);

	sleep(1);
	return $id;
}

function searchDeal($title="", $personId=null)
{
	if (!$personId || !$title) return 0;
    $param = array();
    $param["term"] = $title;
    $param["person_id"] = $personId;

    $myfile = fopen("files/deals.txt", "a+") or die("Unable to open file!");
    $out = array(); $id = $total = 0; $ldate = "";
	$xdate = date("Ymd", strtotime(date("Ymd")." -7 days"));
    while(!feof($myfile)) {
        $line = trim(fgets($myfile));
        $pipe = explode("|", $line);
        if (@$pipe[1] == $personId && strtolower(@$pipe[3]) == strtolower($title)) :
			$id = $pipe[0];
			$ldate = $pipe[2];
		endif;
		if (@$pipe[2] > $xdate) $out[] = $line."\n";
		$total ++;
    }
    fclose($myfile);

	if (count($out) != $total) :
	$fp = fopen("files/deals.txt", "w+");
	flock($fp, LOCK_EX);
	foreach($out as $line) {
    	fwrite($fp, $line);
	}
	flock($fp, LOCK_UN);
	fclose($fp);
	endif;

    if ($id && $ldate && $ldate<=$xdate) return 0;
//    if ($id) return $id;

	if (!$id) :
    $data = callAPI("deals/find", "GET", $param);
    $json = json_decode($data, true);
    $id = @$json["data"][0]["id"];
	endif;

	$cdate = date("Ymd");
	if ($id && $ldate!=$cdate):
    $myfile = fopen("files/deals.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "$id|$personId|$cdate|$title\n");
    fclose($myfile);
	endif;

	sleep(1);
    return $id;
}

function addDeal($title="", $personId, $fields=array())
{
	if (!$personId || !$title) return 0;
    $param = array();
    $param["visible_to"] = 3; // visible to all
	$param["status"] = "open";
	$param["title"] = $title;
	$param["person_id"] = $personId;
	$param["9c192a8a2a0e44fa63c1740599dcd9b692314706"] = date("Y-m-d", strtotime($fields[0])); // date of lead
	$param["113bb5b5edc72957dddfba792f3309c1b9e5075b"] = date("H:i:s", strtotime($fields[0])); // time of lead
	$param["e2c4a2838c16e53c6f4cf3b54ac5bfe253310a7a"] = @$fields[5]; // e-mail
	$param["a7c678234bb7d2d0cc6d6ed484e47103ceebe4b8"] = @$fields[6]; // phone
	$param["80da798cd870548d8ab64811e20616801c6281a2"] = @$fields[13]." ".$fields[10]." ".$fields[11]." ".$fields[14]; // car year brand model motor
	$param["3dede6f73f084572bd612bdbb6a35f34c729e96b"] = @$fields[1]; // lead type
	$param["b3ca2d0037c16896740616bf404b9f91e8e168db"] = @$fields[15]; // primary use of car
	$param["dd72c8b688ddc2a10361f668da6703f2977afdd0"] = @$fields[17]; // previous provider
	$param["c6e0024f5fc8e2d7c445e2bdcf60fb80158f9588"] = @$fields[19]; // mortgagee
	$param["4ff1065459b978511fcf5a14d016c8bf6362b8d5"] = @$fields[20]; // link quote
	$param["b0d9040392ef9708fab88e9f2b20a68b0edda3d8"] = @$fields[2]; // selected provider
	$param["fcd87db3fd38e4dd5a8d6a92c4b444ba11e24245"] = @$fields[23]; // gross premium

    $data = callAPI("deals", "POST", $param);
    $json = json_decode($data, true);
	echo "new deal: ".json_encode($json)."<br>";
    $id = $json["data"]["id"];

	if ($id) :
	$cdate = date("Ymd");
    $myfile = fopen("files/deals.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "$id|$personId|$cdate|$title\n");
    fclose($myfile);
    endif;

	sleep(1);
	return $id;
}

function callAPI($endpoint="", $method="GET", $data = false)
{
	$apiKey = "<your api key here>"; // back office
	$apiUrl = "https://api.pipedrive.com/v1/".$endpoint;
    $ch = curl_init();

    switch ($method) {
        case "POST":
			$apiUrl = "https://api.pipedrive.com/v1/".$endpoint."?api_token=".$apiKey;
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($data) 
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
			$apiUrl = "https://api.pipedrive.com/v1/".$endpoint."?api_token=".$apiKey;
            curl_setopt($ch, CURLOPT_PUT, 1);
            break;
        default:
			$data["api_token"] = $apiKey;
            if ($data)
                $apiUrl = sprintf("%s?%s", $apiUrl, http_build_query($data));
    }

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	$json = curl_exec($ch);
    if (curl_errno($ch)) echo curl_error($ch);
    curl_close($ch);

    return $json;
}

function sendnotif($csvfile="", $total=0, $persons=0, $deals=0) {
	$adminEmail = "youremail@domain.com";
	$adminName = "Pipedrive";

    $mail = new PHPMailer(); // defaults to using php "mail()"
//    $mail->IsSendmail(); // telling the class to use SendMail transport
	$mail->IsSMTP();                             // tell the class to use SMTP
    $mail->SMTPAuth   = true;                    // enable SMTP authentication
    $mail->Port       = 465; // 25;                      // set the SMTP server port
    $mail->Host       = "ssl://smtp.gmail.com";        // SMTP server
    $mail->Username   = "youremail@domain.com";  // SMTP server username
    $mail->Password   = "yourpassword";            // SMTP server password

    $body = "This is to inform you that we already processed $csvfile with $total records.<br>";
	$body .= "New Persons: $persons<br>";
	$body .= "New Deals: $deals<br><br>";
	$body .= "Pipedrive Admin";

	$mail->AddReplyTo($adminEmail, $adminName);
	$mail->SetFrom($adminEmail, $adminName);
	$mail->AddReplyTo($adminEmail, $adminName);

	// recipients
	$mail->AddAddress("paulgonzaga80@gmail.com", "Paul Gonzaga");

	$mail->Subject = "Pipedrive Upload";
	$mail->AltBody = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test
	$mail->MsgHTML($body);

	if (!$mail->Send()) {
  		echo "Mailer Error: " . $mail->ErrorInfo;
	} else {
  		echo "Message sent!";
	}
}

?>
