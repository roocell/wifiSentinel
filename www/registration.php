<?php
//phpinfo();
header("Content-Type: text/json");
error_reporting(E_ALL); ini_set('display_errors', '1');
include("util.php");

// inputs
$action=checkinput('action');
$device_token=checkinput('device_token');

$ACTION_IN_CHECK="check";
$ACTION_IN_CREATE="create";

// returned actions on status=success
// the app can parse for these actions
$ACTION_OUT_CREATED_EXISTING  ="created_existing";
$ACTION_OUT_CREATED_NEW       ="created_new";
$ACTION_OUT_VALIDATED         ="validated";
$ACTION_OUT_UNKNOWN_DEVCIE    ="unknown_device";

// $_SERVER['SERVER_ADDR']
$tg_server_ip="174.112.204.56";
$server_port="18121";
$server_secret="radiussecret";
?>







<?php


$dbname="wifi_sentinel";

$dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=$dbname;charset=utf8;";
$usr = 'root';
$pwd = 'admin123';

try {
    $db = new PDO($dsn, $usr, $pwd);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}


// check for existing token
// we need just a check action so the app can bring up the registration wizard
if ($action==$ACTION_IN_CHECK)
{
  $stmt=$db->query("SELECT * FROM sentinels WHERE device_token='$device_token'");
  if (!$stmt)
  {
    echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
  }
  if($stmt->rowCount() > 0)
  {
    echo json_encode(array("status"=>"success", "action"=>$ACTION_OUT_VALIDATED, "message"=> "found registration"));
  } else {
    echo json_encode(array("status"=>"success", "action"=>$ACTION_OUT_UNKNOWN_DEVCIE, "message"=> "not registered"));
  }
  $db=NULL;
  exit();
}


else if ($action==$ACTION_IN_CREATE)
{
  $apip=checkinput('apip');

  // fixup internal network AP
  if ($apip==$tg_server_ip) $apip="192.168.1.30";

  // it will either be a completely new sentinel
  // or an additional sentinel on the same public IP
  // NOTE: multiple APs will appear as the same public IP

  $stmt=$db->query("SELECT * FROM sentinels WHERE apip='$apip'");
  if (!$stmt)
  {
    echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
  }
  if($stmt->rowCount() > 0)
  {
      $token_match=0;
      while($row = $stmt->fetch(PDO::FETCH_ASSOC))
      {
          if ($device_token==$row['device_token'])
          {
              $token_match=1;
          }
      }
      if (!$token_match)
      {
          // adding a new sentinel for this network
          $sql="INSERT INTO sentinels (device_token, apip) VALUES ('$device_token', '$apip')";
          $rc=$db->query($sql);
          if($rc)
          {
              echo json_encode(array("status"=>"success",
                        "action"=>$ACTION_OUT_CREATED_EXISTING,
                        "message"=> "Created a new sentinel for existing network: $apip",
                        "server_ip" => $tg_server_ip,
                        "server_port" => $server_port,
                        "server_secret" => $server_secret
                        ));
              update_clients_conf($apip);
          } else {
  	          //echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
  	          echo json_encode(array("status"=>"error", "message"=>"failed to insert for existing"));
          }
      } else {
        // found existing token/ap pair
        echo json_encode(array("status"=>"success", "action"=>$ACTION_OUT_VALIDATED, "message"=> "this is an existing token-ap pair"));
      }
  } else {
      // completely new sentinel/ap
      $sql="INSERT INTO sentinels (device_token, apip) VALUES ('$device_token', '$apip')";
      $rc=$db->query($sql);
      if($rc)
      {
          echo json_encode(array("status"=>"success",
                      "action"=>$ACTION_OUT_CREATED_NEW,
                      "message"=> "Created a new sentinel for a new network: $apip",
                      "server_ip" => $tg_server_ip,
                      "server_port" => $server_port,
                      "server_secret" => $server_secret
                    ));
      } else {
          //echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo()); echo $sql;
          echo json_encode(array("status"=>"error", "message"=>"failed to insert for new"));
          update_clients_conf($apip);
      }
  }
}

$db=NULL;
?>
