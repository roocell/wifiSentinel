<?php

echo "\n\n\n================\nSTARTING UP....\n======================\n";

//================================================================
// TODO: these are also defined in util.php - should make it common
$PHPAPACHE_CONTAINER_NAME="myphpcontainer";
$MYSQL_CONTAINER_NAME="mysql";
$CONTAINER_PREFIX="na_fr_";
$DBNAME_PREFIX="radius__"; // dont use '_' in here

$RADIUS_SQL_FILE="schema.sql";

$DOCKER_MACHINE_IP="192.168.1.11";

$PF_FILE="~/wifiSentinel/pf/pf.rules";
$PF_RESTART_CMD="sudo pfctl -F all -f /etc/pf.conf";

function pf_file_entry($_port)
{
  global $DOCKER_MACHINE_IP;
  return "rdr pass on en0 proto udp from any to any port $_port -> $DOCKER_MACHINE_IP port $_port";
}

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
function freeradius_apip_from_contname($_contname)
{
  global $CONTAINER_PREFIX;
  $parts=explode($CONTAINER_PREFIX, $_contname);
  return str_replace("_", ".", $parts[1]);
}

function docker_freeradius_cmd($_apip, $_port)
{
  global $PHPAPACHE_CONTAINER_NAME;
  return "docker run -it -p $_port:1812/udp \\
    -e MYSQL_IP=\$(docker inspect --format='{{.NetworkSettings.IPAddress}}' mysql) \\
    -e MYSQL_PORT=\$(echo \$(docker inspect --format='{{range \$p, \$conf := .Config.ExposedPorts}} {{\$p}} {{end}}' mysql) | awk '{a=\$1; sub(/\\/tcp/, \"\", a); print a}') \\
    -e MYSQL_RADIUS_DB=".freeradius_dbname($_apip)." \\
    -e MYSQL_LOGIN=radius \\
    -e MYSQL_PASSWORD=admin123 \\
    -e APIP=$_apip \\
    -e RADIUS_SECRET=radiussecret \\
    -e NOTIFY_AUTH_IP=\$(docker inspect --format='{{.NetworkSettings.IPAddress}}' $PHPAPACHE_CONTAINER_NAME) \\
    -e NOTIFY_AUTH_PORT=80 \\
    --name ".freeradius_contname($_apip)." \\
    -v ~/wifiSentinel/freeradius/entrypoint_wifi_sentinel.sh:/usr/bin/entrypoint_wifi_sentinel.sh \\
    -d roocell/notifyauth_freeradius entrypoint_wifi_sentinel.sh";
}


//================================================================

$dinghy_running=1;
$mysql_container_running=1;
$phpapache_container_running=1;

# verify docker-machine (dinghy) is running
$dinghy_running = shell_exec('docker-machine ls | grep dinghy');
if (!$dinghy_running)
{
  echo "STARTING DINGHY....\n======================\n";
  system('dinghy destroy');
  system('dinghy create --provider virtualbox'); // this takes very long
  system('docker rm -f dinghy_http_proxy');
}


# verify mysql container is running
$mysql_container_running = shell_exec("docker ps | grep osx_localdb_mysql");
if (!$mysql_container_running)
{
  $mysql_container_built=shell_exec("docker images | grep osx_localdb_mysql");
  if (!$mysql_container_built)
  {
    echo "BUILDING MYSQL CONTAINER....\n======================\n";
    system("docker build -f ~/wifiSentinel/Dockerfile_mysql -t osx_localdb_mysql ~/wifiSentinel/.");
  }
  echo "STARTING MYSQL CONTAINER....\n======================\n";
  system("docker run  -p 3900:3306 --name $MYSQL_CONTAINER_NAME -v ~/wifiSentinel/mysql/datadir:/var/lib/mysql -d osx_localdb_mysql");
  sleep(3); // give some time for the container to start
}


# verify www data volume container is present
$dvc_present = shell_exec("docker ps -a | grep www");
if (!$dvc_present)
{
  echo "CREATING WWW DVC....\n======================\n";
  system("docker create -v ~/wifiSentinel/www:/var/www/html/ --name www roocell/phpapache /bin/true");
  sleep(1);
}

# verify that PHP APACHE container is running (apns)
$phpapache_container_running = shell_exec("docker ps | grep $PHPAPACHE_CONTAINER_NAME");
if (!$phpapache_container_running)
{
  echo "STARTING PHP-APACHE CONTAINER...\n======================\n";
  system("sed -i .bak \"s/.*Servers.*'host'.*/\\\$cfg['Servers'][\\\$i]['host'] = '$(docker inspect --format='{{.NetworkSettings.IPAddress}}' $MYSQL_CONTAINER_NAME):3306';/\" ~/wifiSentinel/www/phpmyadmin/config.inc.php");
  system("docker run --volumes-from www -d -p 11111:80 -p 11112:443 -it --link $MYSQL_CONTAINER_NAME:mysql --name $PHPAPACHE_CONTAINER_NAME roocell/phpapache");

  sleep(1);
}

// TODO: check for radius docker image and download if we don't have it







$MYSQL_PORT = shell_exec("docker inspect --format='{{(index (index .NetworkSettings.Ports \"3306/tcp\") 0).HostPort}}' $MYSQL_CONTAINER_NAME");
$MYSQL_IP   = $DOCKER_MACHINE_IP;

echo "MONITORING SENTINELS (mysql_ip=$MYSQL_IP)....\n======================\n";
while(true)
{
  # monitor wifi_sentinels in DB
  $dsn = "mysql:host=$MYSQL_IP;port=$MYSQL_PORT;dbname=wifi_sentinel;charset=utf8";
  $usr = 'root';
  $pwd = 'admin123';

  try {
    $db = new PDO($dsn, $usr, $pwd);
  } catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
  }
  $sql="SELECT * FROM sentinels";
  $sentinel_results=$db->query($sql);

  # get list of databases on mysql server (there should be one radius db per apip
  $databases=array();
  $sql = "SHOW DATABASES LIKE '$DBNAME_PREFIX%'";
  foreach($db->query($sql) as $row){
    $databases[]=$row[0];
  }
  //var_dump($databases);

  # capture current containers
  $c_apip_arr = array();
  $docker_ps = shell_exec("docker ps --format \"table {{.Names}}\"");
  //echo $docker_ps;
  $containers = explode("\n", $docker_ps);
  $ap_containers=array();
  //echo count($containers)." containers\n";
  foreach ($containers as $c_name)
  {
    //echo "CNAME: ".$c_name."\n";
    //$attribs = preg_split('/\s+/', $container); // explode by whitespace
    if ($c_name == 'NAMES') continue;
    if ($c_name == $MYSQL_CONTAINER_NAME) continue;
    if ($c_name == $PHPAPACHE_CONTAINER_NAME) continue;

    $pos = strpos($c_name, $CONTAINER_PREFIX);
    if ($pos === false) // 3= on purpose - to compare boolean vs position 0
    {
    } else {
      $ap_containers[] = $c_name;
      //echo "APIP: $c_name\n";
    }
  }

  //echo count($sentinel_results)." sentinels\n";
  //var_dump($sentinel_results);
  $sentinels=array();
  while($s = $sentinel_results->fetch(PDO::FETCH_ASSOC))
  {
    $sentinels[]=$s;
    $apip = $s['apip'];
    $port = $s['port'];

    # check if radius_$apip database exists
    $db_exists=0;
    foreach ($databases as $dbname)
    {
      //echo "DB $dbname\n";
      $parts = explode($DBNAME_PREFIX, $dbname);
      $db_apip = $parts[1];
      $db_apip = str_replace("_", ".", $db_apip);
      if ($db_apip == $apip)
      {
        //echo "$apip database exists\n";
        $db_exists=1;
      }
    }
    if (!$db_exists)
    {
      # create a database for this sentinel
      echo "Creating DB for $apip...\n======================\n";
      $creat_db_name=freeradius_dbname($apip);
      $sql="CREATE DATABASE IF NOT EXISTS $creat_db_name";
      if (!$db->query($sql))
      {
        echo "Failed to create DB ($sql)\n";
        echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo());
      }

      $db=NULL; // close previous connection - because we're going to create another below


      $dsn = "mysql:host=$MYSQL_IP;port=$MYSQL_PORT;dbname=$creat_db_name;charset=utf8";
      try {
      $db = new PDO($dsn, $usr, $pwd);
      } catch (PDOException $e) {
        die('Connection failed: ' . $e->getMessage());
      }

      echo "Setting up Schema.....\n======================\n";
      $sql = file_get_contents($RADIUS_SQL_FILE);
      if (!$db->query($sql))
      {
        echo "Failed to create DB ($sql)\n";
        echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo());
      }
      // extend radcheck for our purposes
      $sql="ALTER TABLE `radcheck` ADD `mac` VARCHAR(16) NULL DEFAULT NULL AFTER `value`, ADD `expiry` DATETIME NULL DEFAULT NULL AFTER `mac`";
      if (!$db->query($sql))
      {
        echo "Failed to extend radcheck ($sql)\n";
        echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo());
      }
    }
    // NOTE: keep db open so we can query the database on the next loop


    # freeradius port assignment
    # this is just temporary (will not scale)
    # port assignment is done by the registration process - but this task will make sure things are setup properly
#    $port_exists_in_pf=shell_exec("cat $PF_FILE | grep $port");
#    if (!$port_exists_in_pf)
#    {
#      echo "Editing PF file.....\n======================\n";
#      //file_put_contents($PF_FILE, pf_file_entry($port), FILE_APPEND);
#      system("echo \"".pf_file_entry($port)."\" >> ".$PF_FILE); // append
#      system("sudo pfctl -F all -f /etc/pf.conf"); // restart PF
#      echo "\n======================\n";
#    }

    # check if docker container for that apip is running
    $c_is_running=0;
    $c_name=freeradius_contname($apip);
    //echo "checking containers.....$c_name\n";
    foreach ($ap_containers as $c)
    {
      if ($c_name == $c)
      {
        echo "$apip freeradius is running\n";
        $c_is_running=1;
      }
    } // end of $ap_containers
    if (!$c_is_running)
    {
      // force remove the container in case it exists - but stopped
      system("docker rm -f $c_name");

      # start up any containers
      echo "STARTING CONTAINER $c_name\n======================\n";
      $cmd=docker_freeradius_cmd($apip, $port);
      echo $cmd."\n";
      system($cmd);
    }
  } // end sentinels in DB


  # SENTINEL REMOVAL
  # remove DB, container based on sentinel table
  # (age out of sentinel?)
  foreach ($ap_containers as $c)
  {
    $apip=freeradius_apip_from_contname($c);
    $found=0;
    foreach($sentinels as $s)
    {
      if ($apip == $s['apip'])
      {
        $found=1;
        break;
      }
    }
    if (!$found)
    {
      echo "REMOVING CONTAINER $c\n======================\n";
      system("docker rm -f $c");
      $dbname=freeradius_dbname($apip);
      echo "REMOVING DB $dbname\n======================\n";
      $sql = "DROP DATABASE ".$dbname;
      if (!$db->query($sql))
      {
        echo "Failed to remove DB ($sql)\n";
        echo "\nPDO::errorInfo():\n"; print_r($db->errorInfo());
      }

    }

  }


  sleep(1);
  echo "----------------------------------- ".date("Y-m-d H:i:s")." --------------------------------\n";
}




$db=NULL;





















?>
