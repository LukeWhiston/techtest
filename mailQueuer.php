<?php 

// CheckoutSmart PHP/Mandrill API test
// Author: Luke Whiston


// load Mandrill API
require_once 'mandrill-api-php/src/Mandrill.php'; 
$mandrill = new Mandrill('YOUR_API_KEY'); 


// load database
require_once 'database.php'; 


// Simple mail queue class featuring following capability:
// * addItem - Adds recipient data to mail queue
// * processQueue - Performs cleardown of mail queue utilising Mandrill API
class mailQueuer extends Mandrill
{

	// format email content
	public function formatContent($string, $salutation, $first_name, $last_name)
	{
		$search = array("%salutation%", "%first_name%", "%last_name%"); 
		$replace = array($salutation, $first_name, $last_name); 
		$string = str_replace($search, $replace, $string); 
		return $string; 
	}

	// adds recipient data to mail queue
	public function addItem($params)
	{
		$sql = "INSERT INTO `mailqueue` 
		(
		`date`, 
		`email`, 
		`salutation`, 
		`first_name`, 
		`last_name`, 
		`subject`, 
		`content`, 
		`campaign_id`
		) 
		VALUES
		(
		now(), 
		'".mysql_real_escape_string($params['email'])."', 
		'".mysql_real_escape_string($params['salutation'])."', 
		'".mysql_real_escape_string($params['first_name'])."', 
		'".mysql_real_escape_string($params['last_name'])."', 
		'".mysql_real_escape_string($params['subject'])."', 
		'".mysql_real_escape_string($params['content'])."', 
		'".mysql_real_escape_string($params['campaign_id'])."'
		)"; 
		$result = @mysql_query($sql, $db_handle); 
	}

	// Performs cleardown of mail queue via mail send to Mandrill API
	public function processQueue($campaign_id, $limit)
	{

		// optional campaign id filter
		$filter_cid = ""; // reset
		if($campaign_id > 0)
		{
			$filter_cid = " WHERE `campaign_id` = ".$campaign_id; 
		}

		// optional limit
		$filter_limit = ""; // reset
		if($limit > 0)
		{
			$filter_limit = " LIMIT 0, ".$limit; 
		}

		// select recipients
		$sql = "SELECT * FROM `mailqueue`".$filter_cid." ORDER BY `date` DESC".$filter_limit; 
		$result = @mysql_query($sql, $db_handle); 

		if(mysql_num_rows($result) > 0)
		{

			// loop through mailqueue record
			while($record = @mysql_fetch_array($result))
			{

				// perform send via Mailchimp
				try
				{

					// format content
					$content = formatContent($record['content'], $record['salutation'], $record['first_name'], $record['last_name']); 
					// format subject
					$subject = formatContent($record['subject'], $record['salutation'], $record['first_name'], $record['last_name']); 

					// message variable
					$message = array(
						'html' => $content,
						'text' => strip_tags($content),
						'subject' => $subject,
						'from_email' => 'message.from_email@example.com',
						'from_name' => 'Example Name',
						'to' => array(
									array(
										'email' => $record['email'],
										'name' => $record['salutation'].' '.$record['first_name'].' '.$record['last_name'],
										'type' => 'to'
									)
								),
						'headers' => array('Reply-To' => 'message.reply@example.com'),
						'important' => false,
						'track_opens' => null,
						'track_clicks' => null,
						'auto_text' => null,
						'auto_html' => null
					); 
					$async = false; 
					$ip_pool = 'Main Pool'; 
					$send_at = 'example send_at'; 
					$result = $mandrill->messages->send($message, $async, $ip_pool, $send_at); 
				}
				catch(Mandrill_Error $e)
				{
					// Mandrill errors are thrown as exceptions
					// echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
					// A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
					// throw $e;
				}
	
			}

		}

	}

}


// usage examples
/*

$mQ = new mailQueuer(); 

// add recipient
$params = array(
"email" => "madeupusername@hotmail.com", 
"salutation" => "Mr", 
"first_name" => "Madeup", 
"last_name" => "Username", 
"subject" => "Confirmation of account update: %salutation% %first_name% %last_name%", 
"content" => "Dear %salutation% %first_name% %last_name%, we are sending this email to inform you that your account has been updated successfully.", 
"campaign_id" => 7
); 
$mQ->addItem($params); 

// process everything in queue
$mQ->processQueue(); 

// process queue with campaign ID and limit
$mQ->processQueue(7, 100); 

// command line example - send batch of 100 every hour
00 * * * * wget -O -q "http://www.domainname.com/mailQueuer.php?action=processQueue&campaign_id=7&limit=100"

if($_GET['action'] == "processQueue")
{
	$mQ->processQueue($_GET['campaign_id'], $_GET['limit']); 
}

*/

?>