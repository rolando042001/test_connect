<?php
include "db.php";

$sql = "UPDATE register_state SET active=0, step=0 WHERE id=1";

if($conn->query($sql)){
    echo "REGISTER_FINISHED";
}else{
    echo "ERROR";
}

?>