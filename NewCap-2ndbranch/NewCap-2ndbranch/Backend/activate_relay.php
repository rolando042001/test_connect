<?php
session_start();
include "db.php";
require __DIR__ . "/rbac.php";
require_role('admin');

if(isset($_POST['relay'])){

$relay = intval($_POST['relay']);

$sql = "UPDATE relay SET active=1, fired_at=NOW() WHERE relay_in='$relay'";

if($conn->query($sql)){
    echo "RELAY_ENABLED";
}else{
    echo "ERROR";
}

}
?>