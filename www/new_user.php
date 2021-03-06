<?php
//phpinfo();
header("Content-Type: text/json");
error_reporting(E_ALL); ini_set('display_errors', '1');
include("util.php");

$username=checkinput('username');
$token=checkinput('device_token');
$expiry=checkinput('expiry');
$apip=validate_sentinel($token);

$dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=wifi_sentinel;charset=utf8";
$usr = 'root';
$pwd = 'admin123';

try {
    $db = new PDO($dsn, $usr, $pwd);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

$result = $db->query("SELECT COUNT(*) FROM sentinels WHERE device_token='$token'");
if ($result)
{
	$num=$result->fetchColumn();
} else {
	echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo());
	$num=0;
}
if($num <= 0)
{
	echo json_encode(array ('status' => 'invalid_token'));
	exit();
}
$db = NULL;

// Could also use getenv('MYSQL_PORT_3306_TCP_ADDR')
// But recommended to use the host entry which survives server restart
//$dsn = 'mysql:host='.gethostbyname('mysql');

$dbname=freeradius_dbname($apip);

$dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=$dbname;charset=utf8";
$usr = 'root';
$pwd = 'admin123';

try {
    $db = new PDO($dsn, $usr, $pwd);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// only insert the user if it's not already there
$result = $db->query("SELECT COUNT(*) FROM radcheck WHERE username='$username'");
if ($result)
{
	$num=$result->fetchColumn();
} else {
	echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo());
	$num=0;
}
if($num > 0)
{
	echo json_encode(array("status"=>"success_duplicate", "username"=>$username));
  exit();
}

// insert the new user
if ($expiry==0) $expiry="NULL";
$sql="INSERT INTO radcheck (username, attribute, op, value, expiry) VALUES ('$username', 'Cleartext-Password', ':=', '$username', '$expiry')";
$rc=$db->query($sql);
$sql="INSERT INTO radreply (username, attribute, op, value) VALUES ('$username', 'Session-Timeout', '=', 60)";
$rc=$db->query($sql);
if($rc)
{
    echo json_encode(array("status"=>"success", "username"=>$username));
} else {
	echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
	echo json_encode(array("error"=>"failed to insert"));
}
$db = NULL;

?>
