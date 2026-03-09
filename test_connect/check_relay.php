<?php
include "db.php";

$sql = "SELECT relay_in FROM relay WHERE active=1 LIMIT 1";
$result = $conn->query($sql);

if($row=$result->fetch_assoc()){
echo $row['relay_in'];
}else{
echo "0";
}
?>