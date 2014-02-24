<?php
require dirname ( __FILE__ ) . '/lib/PHPMailer/PHPMailerAutoload.php';

$lastUserIdFile = dirname ( __FILE__ ) . '/last_user_id.txt';
if (! file_exists ( $lastUserIdFile )) {
	file_put_contents ( $lastUserIdFile, '0' );
}
$lastId = intval ( file_get_contents ( $lastUserIdFile ) );

$newsletterContent = file_get_contents ( dirname ( __FILE__ ) . '/newsletters/2014-02-24.html' );

if (file_exists ( dirname ( __FILE__ ) . '/../app/etc/local.xml' )) {
	$dbParams = simplexml_load_file ( dirname ( __FILE__ ) . '/../app/etc/local.xml' );
	$connectionParams = $dbParams->global->resources->default_setup->connection;
	$dsn = 'mysql:dbname=' . $connectionParams->dbname . ';host=' . $connectionParams->host;
	$user = $connectionParams->username;
	$password = $connectionParams->password;
	
	try {
		$dbh = new PDO ( $dsn, $user, $password );
	} catch ( PDOException $e ) {
		echo 'Connection failed: ' . $e->getMessage ();
		exit ();
	}
} else {
	throw new Exception ( "Invalid params file" );
}

$sqlSmtp = "SELECT * FROM  core_config_data WHERE path LIKE  'system\/smtpsettings%'";
foreach ( $dbh->query ( $sqlSmtp ) as $row ) {
	switch ($row ['path']) {
		case 'system/smtpsettings/username' :
			$smtpUsername = $row ['value'];
			break;
		case 'system/smtpsettings/password' :
			$smtpPassword = $row ['value'];
			break;
		case 'system/smtpsettings/host' :
			$smtpHost = $row ['value'];
			break;
		case 'system/smtpsettings/port' :
			$smtpPort = $row ['value'];
			break;
		case 'system/smtpsettings/ssl' :
			$smtpSsl = $row ['value'];
			break;
		case 'system/smtpsettings/authentication' :
			$smtpAuthentication = $row ['value'];
			break;
	}
}

$mail = new PHPMailer ();

$mail->isSMTP (); // Set mailer to use SMTP
$mail->Mailer = "smtp";
$mail->Host = $smtpHost; // Specify main and backup server
$mail->Port = $smtpPort;
$mail->SMTPAuth = true; // Enable SMTP authentication
$mail->Username = $smtpUsername; // SMTP username
$mail->Password = $smtpPassword; // SMTP password
$mail->SMTPSecure = $smtpSsl; // Enable encryption, 'ssl' also accepted
$mail->AuthType = 'PLAIN';
//$mail->SMTPDebug = 0;
$mail->isHTML ( true );

$mail->From = 'contact@bijuteriile-mele.ro';
$mail->FromName = 'Bijuteriile Mele';

$sql = 'SELECT * FROM newsletter_subscriber WHERE subscriber_id > :lastId ORDER BY subscriber_id LIMIT 100';
// echo $sql . " $lastId";
$sth = $dbh->prepare ( $sql, array (
		PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY 
) );
$sth->execute ( array (
		':lastId' => $lastId 
) );

while ( $row = $sth->fetch () ) {
	$userNewsletterCotnent = str_replace ( array (
			'{$subscriberId}',
			'{$subscriberConfirmCode}' 
	), array (
			$row ['subscriber_id'],
			$row ['subscriber_confirm_code'] 
	), $newsletterContent );
	
	$mail->clearAddresses();
	
	$mail->addAddress ( $row ['subscriber_email'] );
	
	$mail->Subject = 'Oferta bijuterii Martisor';
	$mail->Body = $userNewsletterCotnent;
	
	if (! $mail->send ()) {
		echo 'Message could not be sent.';
		echo 'Mailer Error: ' . $mail->ErrorInfo . "\t" . $row ['subscriber_email'] . "\n";
		//print_r($mail);
	} else {
		echo $row ['subscriber_id'] . '-' . $row ['subscriber_email'] . "\n";
	}
	file_put_contents ( $lastUserIdFile, $row ['subscriber_id'] );
}
