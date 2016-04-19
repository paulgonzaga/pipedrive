<?php
// echo phpinfo(); 
 
/**
 *	Gmail attachment extractor.
 *
 *	Downloads attachments from Gmail and saves it to a file.
 *	Uses PHP IMAP extension, so make sure it is enabled in your php.ini,
 *	extension=php_imap.dll
 *
 */
 
 
set_time_limit(3000); 
 
 
/* connect to gmail with your credentials */
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'youremail@domain.com';
$password = 'yourpassword';
 
 
/* try to connect */
$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());
 
 
/* get all new emails. If set to 'ALL' instead 
 * of 'NEW' retrieves all the emails, but can be 
 * resource intensive, so the following variable, 
 * $max_emails, puts the limit on the number of emails downloaded.
 * 
 */
$emails = imap_search($inbox, 'ALL');
 
/* useful only if the above search is set to 'ALL' */
$max_emails = 10;
 
 
/* if any emails found, iterate through each email */
if($emails) {
 
    $count = 1;
 
    /* put the newest emails on top */
    rsort($emails);
 
    /* for every email... */
    foreach($emails as $email_number) 
    {
 
        /* get information specific to this email */
        $overview = imap_fetch_overview($inbox,$email_number,0);
 
        /* get mail message, not actually used here. 
           Refer to http://php.net/manual/en/function.imap-fetchbody.php
           for details on the third parameter.
         */
        $message = imap_fetchbody($inbox,$email_number,2);
 
        /* get mail structure */
        $structure = imap_fetchstructure($inbox, $email_number);
 
        $attachments = array();
 
        /* if any attachments found... */
        if(isset($structure->parts) && count($structure->parts)) 
        {
            for($i = 0; $i < count($structure->parts); $i++) 
            {
                $attachments[$i] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );
 
                if($structure->parts[$i]->ifdparameters) 
                {
                    foreach($structure->parts[$i]->dparameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'filename') 
                        {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }
 
                if($structure->parts[$i]->ifparameters) 
                {
                    foreach($structure->parts[$i]->parameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'name') 
                        {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['name'] = $object->value;
                        }
                    }
                }
 
                if($attachments[$i]['is_attachment']) 
                {
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);
 
                    /* 3 = BASE64 encoding */
                    if($structure->parts[$i]->encoding == 3) 
                    { 
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    }
                    /* 4 = QUOTED-PRINTABLE encoding */
                    elseif($structure->parts[$i]->encoding == 4) 
                    { 
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            }
        }
 
        /* iterate through each attachment and save it */
        foreach($attachments as $attachment)
        {
            if($attachment['is_attachment'] == 1)
            {
                $filename = $attachment['name'];
                if(empty($filename)) $filename = $attachment['filename'];
 
                if(empty($filename)) $filename = time() . ".dat";
 
                /* prefix the email number to the filename in case two emails
                 * have the attachment with the same file name.
                 */
                $fp = fopen("files/input/" . $email_number . "-" . $filename, "w+");
                fwrite($fp, $attachment['attachment']);
                fclose($fp);

				// check and call to process
				echo "processing: files/input/$email_number-$filename\n";
				$id = check($email_number, $filename);
				if (!$id) call($email_number, $filename);	
            }
 
        }
 
        if($count++ >= $max_emails) break;
    }
 
} 
 
/* close the connection */
imap_close($inbox);
 
echo "Done\n";


function call($id=0, $file='')
{
    $ch = curl_init();

	$gfile = $id."-".$file;
	$url = "http://yourdomain.com/pipedrive/index.php?gfile=$gfile";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $json = curl_exec($ch);
    if (curl_errno($ch)) echo curl_error($ch);
    curl_close($ch);

	$myfile = fopen("files/gmail.txt", "a+") or die("Unable to open file!");
    fwrite($myfile, "$id|$file\n");
    fclose($myfile);

    return $json;
}

function check($id=0, $file='')
{
	$emailId = 0;
	$myfile = fopen("files/gmail.txt", "a+") or die("Unable to open file!");
    while(!feof($myfile)) {
        $line = trim(fgets($myfile));
        $pipe = explode("|", $line);
        if ($id==$pipe[0] && strtolower($pipe[1])==strtolower($file)) $emailId = $pipe[0];
    }
    fclose($myfile);
    if ($emailId) return $emailId;
}
 
?>
