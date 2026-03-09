<?php
include "db.php";

if(isset($_POST['relay'])){

$relay = intval($_POST['relay']);

$sql = "UPDATE relay SET active=1 WHERE relay_in='$relay'";

if($conn->query($sql)){
    echo "RELAY_ENABLED";
}else{
    echo "ERROR";
}

}
?>