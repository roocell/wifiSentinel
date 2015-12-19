<?php
//phpinfo();
header("Content-Type: text/json");
error_reporting(E_ALL); ini_set('display_errors', '1');
include("util.php");

$username=checkinput('username');
$token=checkinput('device_token');
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

// insert the new user
$sql="DELETE FROM radcheck WHERE username='$username'";
$rc=$db->query($sql);
if($rc)
{
    echo json_encode(array("status"=>"deleted", "username"=>$username));
} else {
	echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
	echo json_encode(array("error"=>"failed to delete"));
}
$db = NULL;

?>
