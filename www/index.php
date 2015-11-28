<?php
echo "TEST mysql mike";
//phpinfo();

?>



<?php
// Could also use getenv('MYSQL_PORT_3306_TCP_ADDR')
// But recommended to use the host entry which survives server restart
//$dsn = 'mysql:host='.gethostbyname('mysql');
$dsn = "mysql:host=".gethostbyname('mysql').";port=3306";
$usr = 'root';
$pwd = 'admin123';

echo "getenv:".getenv('MYSQL_PORT_3306_TCP_ADDR')."<BR>";
echo "<BR>$dsn";

try {
    $dbh = new PDO($dsn, $usr, $pwd);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

echo 'Connection made!!!';
?>
