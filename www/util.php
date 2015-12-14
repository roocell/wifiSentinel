<?php

function checkinput($input)
{
  if(!isset($_REQUEST[$input]) || !$_REQUEST[$input]!="")
  {
  	$msg = array ('status' => 'invalid_input');
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
  $db=NULL;
}

function update_clients_conf ($apip)
{
   //exec("echo $'client $apip {\n\tsecret = radiussecret\n}' >> ../freeradius/config/clients.conf");
}
?>
