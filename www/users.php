<?php
//phpinfo();
header("Content-Type: text/json");
?>



<?php
// Could also use getenv('MYSQL_PORT_3306_TCP_ADDR')
// But recommended to use the host entry which survives server restart
//$dsn = 'mysql:host='.gethostbyname('mysql');
$dsn = "mysql:host=".gethostbyname('mysql').";port=3306;dbname=radius;charset=utf8";
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
    echo json_encode(array("error"=>"No records found."));
}

$db=NULL;
?>
