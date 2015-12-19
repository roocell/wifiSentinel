<?php
//phpinfo();
error_reporting(E_ALL); ini_set('display_errors', '1');
header("Content-Type: text/json");
include("util.php");

$device_token=checkinput('device_token');

$apip=validate_sentinel($device_token);
?>

<?php
// get user list for this sentinel

// Could also use getenv('MYSQL_PORT_3306_TCP_ADDR')
// But recommended to use the host entry which survives server restart
//$dsn = 'mysql:host='.gethostbyname('mysql');
$dbname=freeradius_dbname($apip);
$dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=$dbname;charset=utf8";
$usr = 'root';
$pwd = 'admin123';

//echo "getenv:".getenv('MYSQL_PORT_3306_TCP_ADDR')."<BR>";
//echo "<BR>$dsn";

try {
    $db = new PDO($dsn, $usr, $pwd);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
$stmt=$db->query('SELECT * FROM radcheck');
if($stmt->rowCount() > 0)
{
    while($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $rows[]=$row;
    }
    echo json_encode(array("success"=>$rows));
} else {
    echo json_encode(array("error"=>"No records found.", "message"=>$dbname));
}

$db=NULL;
?>
