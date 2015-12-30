<?php

$CONTAINER_PREFIX="na_fr_";
$DBNAME_PREFIX="radius__"; // dont use '_' in here

function freeradius_dbname($_apip)
{
  global $DBNAME_PREFIX;
  return $DBNAME_PREFIX.str_replace(".", "_", $_apip);
}
function freeradius_contname($_apip)
{
  global $CONTAINER_PREFIX;
  return $CONTAINER_PREFIX.str_replace(".", "_", $_apip);
}


function checkinput($input)
{
  if(!isset($_REQUEST[$input]) || $_REQUEST[$input]=="")
  {
  	$msg = array ('status' => 'invalid_input missing '.$input);
  	echo json_encode($msg);
  	exit();
  }
 return $_REQUEST[$input];
}


// validate sentinel
function validate_sentinel($device_token)
{
  $dbname="wifi_sentinel";
  $dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=$dbname;charset=utf8;";
  $usr = 'root';
  $pwd = 'admin123';

  try {
      $db = new PDO($dsn, $usr, $pwd);
  } catch (PDOException $e) {
      die('Connection failed: ' . $e->getMessage());
  }

  $stmt=$db->query("SELECT * FROM sentinels WHERE device_token='$device_token'");
  if (!$stmt)
  {
    echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
  }
  if($stmt->rowCount() <= 0)
  {
    echo json_encode(array("status"=>"error", "action"=>"unknown_device", "message"=> "you are not registered"));
    $db=NULL;
    exit();
  }
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $apip=$row['apip'];
  $db=NULL;
  return $apip;
}

function update_clients_conf ($apip)
{
   //exec("echo $'client $apip {\n\tsecret = radiussecret\n}' >> ../freeradius/config/clients.conf");
}
?>
