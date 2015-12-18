<?php

echo "\n\n\n================\nSTARTING UP....\n======================\n";


$PHPAPACHE_CONTAINER_NAME="myphpcontainer";
$MYSQL_CONTAINER_NAME="mysql";

$dinghy_running=1;
$mysql_container_running=1;
$phpapache_container_running=1;

# verify docker-machine (dinghy) is running
$dinghy_running = shell_exec('docker-machine ls | grep dinghy');
if (!$dinghy_running)
{
  echo "STARTING DINGHY....\n======================\n";
  shell_exec('dinghy destroy');
  shell_exec('dinghy create --provider virtualbox'); // this takes very long
  shell_exec('docker rm -f dinghy_http_proxy');
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
  sleep(1);
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
  system("sed -i .bak \"s/.*Servers.*'host'.*/\$cfg['Servers'][\$i]['host'] = '$(docker inspect --format='{{.NetworkSettings.IPAddress}}' $MYSQL_CONTAINER_NAME):3306';/\" ~/wifiSentinel/www/phpmyadmin/config.inc.php");
  system("docker run --volumes-from www -d -p 11111:80 -p 11112:443 -it --link $MYSQL_CONTAINER_NAME:mysql --name $PHPAPACHE_CONTAINER_NAME roocell/phpapache");

  sleep(1);
}



$CONTAINER_PREFIX="na_fr_";
$DBNAME_PREFIX="radius_";

$MYSQL_PORT=shell_exec("docker inspect --format='{{(index (index .NetworkSettings.Ports \"3306/tcp\") 0).HostPort}}' $MYSQL_CONTAINER_NAME");
$MYSQL_IP  ="192.168.99.100";

echo "MONITORING SENTINELS ($MYSQL_IP)....\n======================\n";
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
  $sql = "SHOW DATABASES LIKE '$DBNAME_PREFIX'";
  $databases=$db->query($sql);

  # close db
  $db=NULL;


  # capture current containers
  $c_apip_arr = array();
  $docker_ps = shell_exec('docker ps');
  //echo $docker_ps;
  $containers = explode('\n', $docker_ps);
  foreach ($containers as $container)
  {
    $attribs = preg_split('/\s+/', $container);
    $c_name = $attribs[6];
    if ($c_name == 'NAMES') continue;
    if ($c_name == $MYSQL_CONTAINER_NAME) continue;
    if ($c_name == $PHPAPACHE_CONTAINER_NAME) continue;

    if (strpos($c_name, $CONTAINER_PREFIX) != FALSE)
    {
      $parts = split($CONTAINER_PREFIX, $c_name);               
      $c_apip_arr[] = $parts[1];
      echo "APIP: ".$parts[1];
    }
  }


  foreach ($sentinel_results as $row)
  {
    $apip = $row['apip'];

    # check if radius_$apip database exists
    $db_exists=0;
    foreach ($databases as $dbname)
    {
      $parts = explode("radius_", $dbname);
      $db_apip = $parts[1];
      if ($db_apip == $apip)
      {
        echo "$apip database exists";
        $db_exists=1;
      }
    }
    if (!$db_exists)
    {
      # create a database for this sentinel
    }



    # check if docker container for that apip is running
    $c_is_running=0;
    foreach ($c_apip_arr as $c_apip)
    {
      if ($c_apip == $apip)
      {
        echo "$apip freeradius is running\n";
        $c_is_running=1;
      }
    }
    if (!$c_is_running)
    {
      # start up any containers
      $c_name=$CONTAINER_PREFIX.$apip;
      echo "starting container $c_name\n";
    }



  }


  # stop any containers
  # (age out of sentinel?)
  # DROP DATABASE $DBNAME_PREFIX.$apip

  sleep(1);
}


























?>
