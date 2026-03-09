<?php
include "db.php";

$sql = "SELECT active, step FROM register_state WHERE id=1";
$result = $conn->query($sql);

if($row = $result->fetch_assoc()){
    echo $row['active'] . "," . $row['step'];
}

?>