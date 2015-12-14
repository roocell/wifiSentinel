<?php
header("Content-Type: text/json");
error_reporting(E_ALL); ini_set('display_errors', '1');

if(!isset($_REQUEST['username']) || $_REQUEST['username']=="")
{
	$msg = array ('status' => 'invalid_input');
	echo json_encode($msg);
	exit();
}
if(!isset($_REQUEST['apip']) || $_REQUEST['apip']=="")
{
	$msg = array ('status' => 'invalid_input');
	echo json_encode($msg);
	exit();
}

$username=$_REQUEST['username'];
$apip=$_REQUEST['apip'];

// get token based on $apip
$dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=wifi_sentinel;charset=utf8";
$usr = 'root';
$pwd = 'admin123';

try {
    $db = new PDO($dsn, $usr, $pwd);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
$deviceToken=0;
$sql="SELECT * FROM sentinels WHERE apip='$apip'";
	foreach ($db->query($sql) as $row) {
			// TODO: support multiple sentinels per ap
			$deviceToken = $row['device_token'];
	}
if ($deviceToken)
{
  $token_status="found";
} else {
	// Put your device token here (without spaces):
	$deviceToken = '7660ed22f31a1eab54d46edbb7c6c932d190877365a0acced40487037d0a68ec';
	//$deviceToken = 'd4d27240ffeb2a3d5586adad80089e350dcb6ac4b90f6195963d88d2d4d4e117';
	$token_status="default";
}
$db=NULL;

// Put your private key's passphrase here:
$passphrase = 'admin123';

// Put your alert message here:
$data = array ('username' => $username, 'apip' => $apip);
$message = $username." would like to join your wifi";

////////////////////////////////////////////////////////////////////////////////

$ctx = stream_context_create();
stream_context_set_option($ctx, 'ssl', 'local_cert', 'ck.pem');
stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);

// Open a connection to the APNS server
$fp = stream_socket_client(
	'ssl://gateway.sandbox.push.apple.com:2195', $err,
	$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

if (!$fp)
{
	$status = "connect_failed";
}

// Create the payload body
$body['aps'] = array(
	'alert' => $message,
	'sound' => 'default',
        'content-available' => '1'
	);
$body['data'] = $data;

// Encode the payload as JSON
$payload = json_encode($body);

// Build the binary notification
$msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

// Send it to the server
$result = fwrite($fp, $msg, strlen($msg));

if (!$result)
	$status = "not_delivered";
else
	$status = "success";

// Close the connection to the server
fclose($fp);

$reponse=array('status' => $status, 'token_status' => $token_status, 'token' => $deviceToken);
echo json_encode($reponse);
